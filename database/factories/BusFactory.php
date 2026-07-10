<?php

namespace Database\Factories;

use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bus>
 */
class BusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate_number' => fake()->bothify('??-#######'),
            'manufacturer' => fake()->word(),
            'model' => fake()->word(),
            'production_year' => fake()-> numberBetween(2012,2026),
            'capacity' => fake()->numberBetween(25,50),
            'status' => fake()->randomElement(['in_service', 'out_of_service','maintenance']),
        ];
    }
}
