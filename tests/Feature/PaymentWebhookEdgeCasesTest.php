<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hostile and unusual input for the payment webhook: malformed signatures and
 * timestamps, odd bodies and content types, and replays.
 */
class PaymentWebhookEdgeCasesTest extends TestCase
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

    private function body(array $overrides = []): string
    {
        return json_encode(array_replace([
            'id' => 'evt_edge',
            'type' => 'payment.succeeded',
            'data' => ['booking_id' => $this->booking->id],
        ], $overrides));
    }

    /** POST with raw header values, so tests can send anything at all. */
    private function send(string $body, string $timestamp, string $signature, string $contentType = 'application/json', string $uri = '/api/webhooks/payments'): TestResponse
    {
        return $this->call('POST', $uri, server: $this->transformHeadersToServerVars([
            'Content-Type' => $contentType,
            'Accept' => 'application/json',
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => $signature,
        ]), content: $body);
    }

    private function sendSigned(string $body, ?int $timestamp = null, string $contentType = 'application/json', string $uri = '/api/webhooks/payments'): TestResponse
    {
        $timestamp ??= now()->getTimestamp();

        return $this->send($body, (string) $timestamp, hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET), $contentType, $uri);
    }

    private function assertStillPending(): void
    {
        $this->assertSame(Booking::PENDING, $this->booking->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    // --- Signature format ----------------------------------------------------

    public function test_an_uppercase_hex_signature_is_rejected(): void
    {
        $ts = (string) now()->getTimestamp();
        $body = $this->body();

        $this->send($body, $ts, strtoupper(hash_hmac('sha256', $ts.'.'.$body, self::SECRET)))->assertUnauthorized();
        $this->assertStillPending();
    }

    public function test_a_truncated_signature_is_rejected(): void
    {
        $ts = (string) now()->getTimestamp();
        $body = $this->body();

        $this->send($body, $ts, substr(hash_hmac('sha256', $ts.'.'.$body, self::SECRET), 0, 32))->assertUnauthorized();
        $this->send($body, $ts, '')->assertUnauthorized();
        $this->assertStillPending();
    }

    // --- Timestamp format and window -----------------------------------------

    public function test_malformed_timestamps_are_rejected_with_401_not_500(): void
    {
        $body = $this->body();

        foreach (['abc', '-100', '1.9e9', ' ', '99999999999999999999999999'] as $timestamp) {
            $this->send($body, $timestamp, hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET))
                ->assertUnauthorized();
        }

        $this->assertStillPending();
    }

    public function test_a_timestamp_too_far_in_the_future_is_rejected(): void
    {
        $this->sendSigned($this->body(), now()->addMinutes(6)->getTimestamp())->assertUnauthorized();
        $this->assertStillPending();
    }

    public function test_the_window_edge_is_inclusive(): void
    {
        $this->sendSigned($this->body(['id' => 'evt_past']), now()->subSeconds(300)->getTimestamp())->assertOk();
        $this->sendSigned($this->body(['id' => 'evt_late']), now()->subSeconds(301)->getTimestamp())->assertUnauthorized();
    }

    // --- Body shape ----------------------------------------------------------

    public function test_a_signed_empty_body_returns_422(): void
    {
        $this->sendSigned('')->assertUnprocessable();
        $this->assertStillPending();
    }

    public function test_signed_invalid_json_returns_422(): void
    {
        $this->sendSigned('{"id": "evt_broken", "type":')->assertUnprocessable();
        $this->assertStillPending();
    }

    public function test_data_as_a_string_returns_422(): void
    {
        $this->sendSigned($this->body(['data' => 'booking 1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.booking_id');
        $this->assertStillPending();
    }

    public function test_a_numeric_string_booking_id_is_accepted(): void
    {
        $this->sendSigned($this->body(['data' => ['booking_id' => (string) $this->booking->id]]))
            ->assertOk()
            ->assertJson(['outcome' => 'confirmed']);
    }

    public function test_a_fractional_booking_id_is_rejected(): void
    {
        $this->sendSigned($this->body(['data' => ['booking_id' => $this->booking->id + 0.5]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.booking_id');
        $this->assertStillPending();
    }

    public function test_event_ids_longer_than_255_characters_are_rejected(): void
    {
        $this->sendSigned($this->body(['id' => str_repeat('e', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');

        $this->sendSigned($this->body(['id' => str_repeat('e', 255)]))->assertOk();
    }

    // --- Replays -------------------------------------------------------------

    public function test_replaying_an_event_with_a_fresh_timestamp_is_still_a_duplicate(): void
    {
        $body = $this->body(['id' => 'evt_replay']);

        $this->sendSigned($body)->assertJson(['status' => 'processed']);

        $this->travel(10)->minutes();

        $this->sendSigned($body)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertDatabaseCount('webhook_events', 1);
    }
}
