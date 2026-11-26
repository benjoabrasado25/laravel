<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'guest_name', 'check_in', 'check_out', 'status'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** Only confirmed bookings block dates. */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', self::CONFIRMED);
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    /**
     * Apply a payment provider event to this booking and say what happened.
     *
     * Rule: payment events only move a booking OUT OF PENDING. Providers can
     * deliver events late or out of order, so they never change a booking
     * that is already confirmed or cancelled.
     *
     *   pending   + payment.succeeded  ->  confirmed ("clash" if the dates were taken meanwhile)
     *   pending   + payment.failed     ->  cancelled
     *   cancelled + payment.succeeded  ->  "needs_review": paid for a cancelled stay, so refund it
     *   anything else                  ->  "unchanged" ("ignored" for event types we don't handle)
     */
    public function applyPaymentEvent(string $type): string
    {
        if (! in_array($type, ['payment.succeeded', 'payment.failed'], true)) {
            return 'ignored';
        }

        if ($this->status !== self::PENDING) {
            return $this->status === self::CANCELLED && $type === 'payment.succeeded'
                ? 'needs_review'
                : 'unchanged';
        }

        if ($type === 'payment.failed') {
            $this->update(['status' => self::CANCELLED]);

            return 'cancelled';
        }

        // The same overlap rule as the booking form: payment can't double-book a property.
        $clash = $this->property->findOverlappingBooking(
            $this->check_in->toDateString(),
            $this->check_out->toDateString(),
        );

        if ($clash) {
            return 'clash';
        }

        $this->update(['status' => self::CONFIRMED]);

        return 'confirmed';
    }
}
