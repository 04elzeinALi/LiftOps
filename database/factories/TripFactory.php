<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Schedule;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'schedule_id'=>Schedule::factory(),
             'bus_id'=>Bus::factory(),
             'driver_id'=>Driver::factory(),
            'trip_date' => fake()->date(),
            'actual_departure' => fake()->dateTime(),
            'actual_arrival' => fake()->dateTime(),
            'status' => fake()->randomElement(['scheduled', 'ongoing','completed','cancelled']),
        ];
    }
}
