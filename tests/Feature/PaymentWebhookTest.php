<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payments.webhook_secret' => self::SECRET]);
        $this->travelTo('2030-03-01 12:00:00');

        $this->booking = Booking::factory()->pending()->create([
            'check_in' => '2030-03-10',
            'check_out' => '2030-03-15',
        ]);
    }

    /** Build an event body like the provider would send. */
    private function event(string $type = 'payment.succeeded', string $id = 'evt_1', ?int $bookingId = null): string
    {
        return json_encode([
            'id' => $id,
            'type' => $type,
            'data' => ['booking_id' => $bookingId ?? $this->booking->id],
        ]);
    }

    /**
     * POST a raw body with signature headers. By default it signs the body
     * correctly with the current time; the arguments let a test break that.
     */
    private function sendWebhook(string $body, ?string $signature = null, ?int $timestamp = null, string $secret = self::SECRET): TestResponse
    {
        $timestamp ??= now()->getTimestamp();
        $signature ??= hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this->call('POST', '/api/webhooks/payments', server: $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ]), content: $body);
    }

    // --- Happy paths ---------------------------------------------------------

    public function test_a_successful_payment_confirms_the_booking(): void
    {
        $this->sendWebhook($this->event('payment.succeeded'))
            ->assertOk()
            ->assertJson(['status' => 'processed', 'outcome' => 'confirmed']);

        $this->assertSame(Booking::CONFIRMED, $this->booking->fresh()->status);
    }

    public function test_a_failed_payment_cancels_the_booking(): void
    {
        $this->sendWebhook($this->event('payment.failed'))
            ->assertOk()
            ->assertJson(['outcome' => 'cancelled']);

        $this->assertSame(Booking::CANCELLED, $this->booking->fresh()->status);
    }

    public function test_the_event_is_recorded(): void
    {
        $this->sendWebhook($this->event('payment.succeeded', 'evt_abc'));

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt_abc',
            'type' => 'payment.succeeded',
            'booking_id' => $this->booking->id,
            'outcome' => 'confirmed',
        ]);
        $this->assertSame('evt_abc', WebhookEvent::sole()->payload['id']);
    }

    public function test_the_event_is_written_to_the_log(): void
    {
        Log::spy();

        $this->sendWebhook($this->event('payment.succeeded', 'evt_log'));

        Log::shouldHaveReceived('info')->with('Payment webhook processed', [
            'event_id' => 'evt_log',
            'type' => 'payment.succeeded',
            'booking_id' => $this->booking->id,
            'outcome' => 'confirmed',
        ])->once();
    }

    // --- Signature checks ----------------------------------------------------

    public function test_a_wrong_signature_is_rejected(): void
    {
        $this->sendWebhook($this->event(), signature: str_repeat('0', 64))->assertUnauthorized();

        $this->assertSame(Booking::PENDING, $this->booking->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_a_body_signed_with_another_secret_is_rejected(): void
    {
        $this->sendWebhook($this->event(), secret: 'someone-elses-secret')->assertUnauthorized();
    }

    public function test_a_body_changed_after_signing_is_rejected(): void
    {
        $timestamp = now()->getTimestamp();
        $signedFor = $this->event('payment.failed');

        $this->sendWebhook(
            $this->event('payment.succeeded'), // tampered body
            signature: hash_hmac('sha256', $timestamp.'.'.$signedFor, self::SECRET),
            timestamp: $timestamp,
        )->assertUnauthorized();
    }

    public function test_missing_signature_headers_are_rejected(): void
    {
        $this->call('POST', '/api/webhooks/payments', server: $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), content: $this->event())->assertUnauthorized();
    }

    public function test_an_old_signed_request_cannot_be_replayed(): void
    {
        $sixMinutesAgo = now()->subMinutes(6)->getTimestamp();

        $this->sendWebhook($this->event(), timestamp: $sixMinutesAgo)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Signature timestamp is too old or in the future.']);
    }

    public function test_a_request_within_the_five_minute_window_is_accepted(): void
    {
        $this->sendWebhook($this->event(), timestamp: now()->subMinutes(4)->getTimestamp())->assertOk();
    }

    public function test_every_request_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.payments.webhook_secret' => null]);

        $this->sendWebhook($this->event(), secret: '')->assertServerError();

        $this->assertSame(Booking::PENDING, $this->booking->fresh()->status);
    }

    // --- Duplicates ----------------------------------------------------------

    public function test_a_duplicate_event_is_ignored(): void
    {
        $this->sendWebhook($this->event('payment.succeeded', 'evt_dup'))->assertJson(['status' => 'processed']);

        // Same event id again, even with a different type, must change nothing.
        $this->sendWebhook($this->event('payment.failed', 'evt_dup'))
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(Booking::CONFIRMED, $this->booking->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    // --- Business rules ------------------------------------------------------

    public function test_payment_does_not_confirm_a_booking_that_now_clashes(): void
    {
        Booking::factory()->for($this->booking->property)->create([
            'check_in' => '2030-03-12',
            'check_out' => '2030-03-14',
        ]);

        $this->sendWebhook($this->event('payment.succeeded'))
            ->assertOk()
            ->assertJson(['outcome' => 'clash']);

        $this->assertSame(Booking::PENDING, $this->booking->fresh()->status);
    }

    public function test_payment_for_an_already_confirmed_booking_changes_nothing(): void
    {
        $this->booking->update(['status' => Booking::CONFIRMED]);

        $this->sendWebhook($this->event('payment.succeeded'))->assertJson(['outcome' => 'unchanged']);
    }

    public function test_unknown_event_types_are_recorded_but_ignored(): void
    {
        $this->sendWebhook($this->event('payment.refund_requested'))
            ->assertOk()
            ->assertJson(['outcome' => 'ignored']);

        $this->assertSame(Booking::PENDING, $this->booking->fresh()->status);
        $this->assertDatabaseHas('webhook_events', ['type' => 'payment.refund_requested', 'outcome' => 'ignored']);
    }

    // --- Bad input -----------------------------------------------------------

    public function test_an_unknown_booking_returns_404_and_is_not_recorded(): void
    {
        $this->sendWebhook($this->event(bookingId: 999))->assertNotFound();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_an_invalid_payload_returns_422(): void
    {
        $this->sendWebhook(json_encode(['type' => 'payment.succeeded']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id', 'data.booking_id']);
    }

    public function test_the_webhook_lives_on_the_api_route(): void
    {
        // API routes have no sessions or CSRF, which a provider's server can't supply.
        $this->assertSame('/api/webhooks/payments', parse_url(route('webhooks.payments'), PHP_URL_PATH));
    }
}
