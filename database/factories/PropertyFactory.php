<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $adjective = fake()->randomElement(['Sunny', 'Quiet', 'Old Town', 'Riverside', 'Garden', 'Hilltop', 'Harbour', 'Bright']);
        $type = fake()->randomElement(['Loft', 'Apartment', 'Studio', 'Townhouse', 'Villa', 'Flat']);

        return [
            'name' => "{$adjective} {$type}",
            'city' => fake()->randomElement(['Lisbon', 'Porto', 'Barcelona', 'Valencia']),
            'price_per_night' => fake()->numberBetween(6, 30) * 5, // €30 to €150, in steps of 5
            'bedrooms' => fake()->numberBetween(1, 4),
        ];
    }
}
