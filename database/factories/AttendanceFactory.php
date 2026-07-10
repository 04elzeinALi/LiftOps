<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'trip_id' => Trip::factory(),
            'check_in' => fake()->dateTime(),
            'check_out' => fake()->dateTime(),
            'status' => fake()->randomElement(['present', 'absent', 'late']),
        ];
    }
}
