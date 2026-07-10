<?php

namespace Database\Factories;

use App\Models\ScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Schedule;

/**
 * @extends Factory<ScheduleDay>
 */
class ScheduleDayFactory extends Factory
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
            'day_of_week' => fake()->randomElement(['sunday', 'monday','tuesday','wednesday','thursday','friday','saturday']),
            
        ];
    }
}
