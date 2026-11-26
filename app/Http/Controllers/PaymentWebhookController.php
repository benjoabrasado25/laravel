<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $event = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'data.booking_id' => ['required', 'integer'],
        ]);

        $booking = Booking::find($event['data']['booking_id']);

        // 404 makes the provider retry later; we don't record the event, so the retry can still succeed.
        if (! $booking) {
            Log::warning('Payment webhook for unknown booking', ['event_id' => $event['id'], 'booking_id' => $event['data']['booking_id']]);

            return response()->json(['message' => 'Booking not found.'], 404);
        }

        try {
            // One transaction: recording the event and changing the booking succeed or fail together.
            $outcome = DB::transaction(function () use ($event, $booking, $request) {
                // Inserting first claims the event id. A duplicate throws here,
                // even if two deliveries arrive at the same moment.
                $record = WebhookEvent::create([
                    'event_id' => $event['id'],
                    'type' => $event['type'],
                    'booking_id' => $booking->id,
                    'payload' => $request->json()->all(),
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

        Log::info('Payment webhook processed', [
            'event_id' => $event['id'],
            'type' => $event['type'],
            'booking_id' => $booking->id,
            'outcome' => $outcome,
        ]);

        return response()->json(['status' => 'processed', 'outcome' => $outcome]);
    }
}
