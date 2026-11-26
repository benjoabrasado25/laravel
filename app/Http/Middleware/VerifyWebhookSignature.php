<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks that a payment webhook really comes from the provider.
 *
 * The provider sends two headers:
 *   X-Webhook-Timestamp: Unix time when it signed the request
 *   X-Webhook-Signature: hex HMAC-SHA256 of "{timestamp}.{raw body}" with our shared secret
 *
 * We recompute the HMAC and compare. Signing the timestamp too means an
 * old request can't be replayed after the tolerance window.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.payments.webhook_secret');

        // Fail closed: without a secret we can't verify anything, so accept nothing.
        if (! $secret) {
            Log::error('Payment webhook rejected: PAYMENTS_WEBHOOK_SECRET is not set.');
            abort(500, 'Webhook secret is not configured.');
        }

        $timestamp = $request->header('X-Webhook-Timestamp');
        $signature = $request->header('X-Webhook-Signature');

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature)) {
            return $this->reject($request, 'Missing or malformed signature headers.');
        }

        $tolerance = (int) config('services.payments.webhook_tolerance', 300);

        if (abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return $this->reject($request, 'Signature timestamp is too old or in the future.');
        }

        // Sign the raw body exactly as received; re-encoding the JSON could change it.
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        // hash_equals takes the same time whatever the input, so it leaks nothing about the secret.
        if (! hash_equals($expected, $signature)) {
            return $this->reject($request, 'Invalid signature.');
        }

        return $next($request);
    }

    private function reject(Request $request, string $reason): Response
    {
        Log::warning('Payment webhook rejected: '.$reason, ['ip' => $request->ip()]);

        return response()->json(['message' => $reason], 401);
    }
}
