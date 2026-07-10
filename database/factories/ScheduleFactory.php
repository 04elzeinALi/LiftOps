<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Route;


/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id'=>Route::factory(),
            'departure_time' => fake() ->time(),
            'arrival_time' => fake()->time(),
        ];
    }
}
