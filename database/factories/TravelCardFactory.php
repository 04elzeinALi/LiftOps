<?php

namespace Database\Factories;

use App\Models\Passenger;
use App\Models\TravelCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Route;

/**
 * @extends Factory<TravelCard>
 */
class TravelCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id'=>Passenger::factory(),
            'route_id'=>Route::factory(),
            'card_type' => fake()->randomElement(['single', 'return', 'weekly', 'monthly']),
            'purchase_date' => fake()->date(),
            'expiry_date' => fake()->date(),
            'total_trips' => fake()->numberBetween(1, 20),
            'status' => fake()->randomElement(['active', 'expired','suspended']),

        ];
    }
}
