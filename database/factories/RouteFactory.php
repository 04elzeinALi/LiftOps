<?php

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_name' => fake() ->word(),
            'origin' => fake()->word(),
            'destination' => fake()->word(),
            'distance_km' => fake()-> randomFloat(2,5,100),
            'estimated_duration_hours' => fake()->numberBetween(1, 3) . 'h ' . fake()->numberBetween(0, 59) . 'm',
            'fare' => fake()->randomFloat(2, 50, 500)
        ];
    }
}
