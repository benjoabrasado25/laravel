<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'city', 'price_per_night', 'bedrooms'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Find a confirmed booking that clashes with the requested stay, if any.
     *
     * A stay covers the nights from check-in up to (not including) check-out,
     * so two stays overlap only when each one starts before the other ends:
     *
     *     existing.check_in < new.check_out  AND  existing.check_out > new.check_in
     *
     * Strict "<" and ">" mean back-to-back stays are allowed: one guest can
     * check in on the same day the previous guest checks out.
     */
    public function findOverlappingBooking(string $checkIn, string $checkOut): ?Booking
    {
        return $this->bookings()
            ->confirmed()
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn)
            ->orderBy('check_in')
            ->first();
    }
}
