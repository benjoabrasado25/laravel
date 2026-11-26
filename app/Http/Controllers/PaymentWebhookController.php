<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Receives payment events. The signature has already been checked by the
 * VerifyWebhookSignature middleware before this runs.
 *
 * Expected body: {"id": "evt_123", "type": "payment.succeeded", "data": {"booking_id": 5}}
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Read ONLY the signed JSON body. $request->validate() would also read the
        // query string, which the signature doesn't cover.
        $payload = $request->json()->all();

        $event = Validator::make($payload, [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'data.booking_id' => ['required', 'integer'],
        ])->validate();

        $booking = Booking::find($event['data']['booking_id']);

        // 404 makes the provider retry later; we don't record the event, so the retry can still succeed.
        if (! $booking) {
            Log::warning('Payment webhook for unknown booking', ['event_id' => $event['id'], 'booking_id' => $event['data']['booking_id']]);

            return response()->json(['message' => 'Booking not found.'], 404);
        }

        try {
            // One transaction: recording the event and changing the booking succeed or fail together.
            $outcome = DB::transaction(function () use ($event, $booking, $payload) {
                // Inserting first claims the event id. A duplicate throws here,
                // even if two deliveries arrive at the same moment.
                $record = WebhookEvent::create([
                    'event_id' => $event['id'],
                    'type' => $event['type'],
                    'booking_id' => $booking->id,
                    'payload' => $payload,
                ]);

                $outcome = $booking->applyPaymentEvent($event['type']);
                $record->update(['outcome' => $outcome]);

                return $outcome;
            });
        } catch (UniqueConstraintViolationException) {
            Log::info('Payment webhook duplicate ignored', ['event_id' => $event['id']]);

            // 200 so the provider stops retrying an event we already handled.
            return response()->json(['status' => 'duplicate']);
        }

        $context = [
            'event_id' => $event['id'],
            'type' => $event['type'],
            'booking_id' => $booking->id,
            'outcome' => $outcome,
        ];

        // "clash" and "needs_review" mean the money and the booking disagree, so a person should look.
        if (in_array($outcome, ['clash', 'needs_review'], true)) {
            Log::warning('Payment webhook needs review', $context);
        } else {
            Log::info('Payment webhook processed', $context);
        }

        return response()->json(['status' => 'processed', 'outcome' => $outcome]);
    }
}
