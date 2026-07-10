<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\ScheduleDay;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = Schedule::all();

        foreach ($schedules as $schedule) {
            ScheduleDay::factory()->count(1)->create([
                'schedule_id' => $schedule->id,
            ]);
        }
    }
}
