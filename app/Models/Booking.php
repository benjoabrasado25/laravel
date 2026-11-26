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
     * - payment.succeeded confirms the booking, unless its dates now clash with
     *   another confirmed booking. Then it stays as it is and needs a person to
     *   sort out a refund.
     * - payment.failed cancels the booking.
     * - Any other event type is ignored.
     */
    public function applyPaymentEvent(string $type): string
    {
        return match ($type) {
            'payment.succeeded' => $this->confirmAfterPayment(),
            'payment.failed' => $this->changeStatus(self::CANCELLED),
            default => 'ignored',
        };
    }

    private function confirmAfterPayment(): string
    {
        if ($this->status === self::CONFIRMED) {
            return 'unchanged';
        }

        // The same overlap rule as the booking form: payment can't double-book a property.
        $clash = $this->property->findOverlappingBooking(
            $this->check_in->toDateString(),
            $this->check_out->toDateString(),
        );

        return $clash ? 'clash' : $this->changeStatus(self::CONFIRMED);
    }

    private function changeStatus(string $status): string
    {
        if ($this->status === $status) {
            return 'unchanged';
        }

        $this->update(['status' => $status]);

        return $status;
    }
}
