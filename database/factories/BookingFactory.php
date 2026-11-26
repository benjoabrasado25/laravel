<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = now()->startOfDay()->addDays(fake()->numberBetween(1, 60));

        return [
            'property_id' => Property::factory(),
            'guest_name' => fake()->firstName().' '.fake()->lastName(), // generated, not a real person
            'check_in' => $checkIn,
            'check_out' => $checkIn->copy()->addDays(fake()->numberBetween(1, 7)),
            'status' => Booking::CONFIRMED,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => Booking::PENDING]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Booking::CANCELLED]);
    }
}
