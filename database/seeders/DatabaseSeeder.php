<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 8 properties (2 per city) and 20 bookings of dummy data.
     */
    public function run(): void
    {
        // Fixed seed, so you get the same names and prices every time you reseed.
        fake()->seed(2026);

        $properties = Property::factory()
            ->count(8)
            ->state(new Sequence(
                ['name' => 'Alfama Rooftop Loft', 'city' => 'Lisbon', 'bedrooms' => 1],
                ['name' => 'Ribeira River Flat', 'city' => 'Porto', 'bedrooms' => 2],
                ['name' => 'Gothic Quarter Studio', 'city' => 'Barcelona', 'bedrooms' => 1],
                ['name' => 'Ruzafa Garden House', 'city' => 'Valencia', 'bedrooms' => 3],
                ['name' => 'Belém Family Townhouse', 'city' => 'Lisbon', 'bedrooms' => 4],
                ['name' => 'Foz Ocean View Apartment', 'city' => 'Porto', 'bedrooms' => 2],
                ['name' => 'Gràcia Sunny Terrace', 'city' => 'Barcelona', 'bedrooms' => 2],
                ['name' => 'Malvarrosa Beach Villa', 'city' => 'Valencia', 'bedrooms' => 4],
            ))
            ->create();

        // Each property's bookings are placed one after another, starting a
        // little before today, so the seed data never breaks the overlap rule.
        $nextFreeDay = [];
        foreach ($properties as $property) {
            $nextFreeDay[$property->id] = now()->startOfDay()->subDays(10);
        }

        for ($i = 0; $i < 20; $i++) {
            $property = $properties[$i % $properties->count()];

            $checkIn = $nextFreeDay[$property->id]->copy()->addDays(fake()->numberBetween(0, 4));
            $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(2, 6));
            $nextFreeDay[$property->id] = $checkOut;

            Booking::factory()->for($property)->create([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => match (true) {
                    $i % 7 === 3 => Booking::PENDING,
                    $i % 9 === 5 => Booking::CANCELLED,
                    default => Booking::CONFIRMED,
                },
            ]);
        }
    }
}
