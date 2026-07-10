<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Trip;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\Driver;

class TripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            $schedules = Schedule::all();
            $buses = Bus::all();
            $drivers = Driver::all();

             foreach ($schedules as $schedule){
                Trip::factory()->create([
                    'schedule_id' => $schedule->id,
                    'bus_id' => $buses->random()->id,
                    'driver_id' => $drivers->random()->id,
                ]);
    }
}

}
