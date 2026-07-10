<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
       
          $this->call([
        UserSeeder::class,
        DriverSeeder::class,
        BusSeeder::class,
        StationSeeder::class,
        RouteSeeder::class,
        ScheduleSeeder::class,
        ScheduleDaySeeder::class,
        PassengerSeeder::class,
        TravelCardSeeder::class,
        TripSeeder::class,
        ReservationSeeder::class,
        BoardingSeeder::class,
        PaymentSeeder::class,
        MaintenanceSeeder::class,
        AttendanceSeeder::class,
         
    ]);
    }
}
