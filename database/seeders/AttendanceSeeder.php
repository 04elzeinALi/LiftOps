<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = Driver::all();
        $trips = Trip::all();

        foreach ($drivers as $driver) {
            Attendance::factory()->create([
                'driver_id' => $driver->id,
                'trip_id' => $trips->random()->id,
            ]);
    }
}

}
