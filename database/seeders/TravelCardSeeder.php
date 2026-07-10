<?php

namespace Database\Seeders;

use App\Models\Passenger;
use App\Models\Route;
use App\Models\TravelCard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TravelCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            $routes = Route::all();
            $passengers = Passenger::all();

            foreach ($passengers as $passenger){
                TravelCard::factory()->create([
                    'passenger_id' => $passenger->id,
                    'route_id' => $routes->random()->id,
                ]);
            }

    }
}
