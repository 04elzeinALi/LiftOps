<?php

namespace Database\Seeders;

use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
             $trips = Trip::all();
            $travelCards = TravelCard::all();

             foreach ($travelCards as $travelCard){
                Reservation::factory()->create([
                    'passenger_id' => $travelCard->passenger_id,
                    'trip_id' => $trips->random()->id,
                    'travel_card_id' => $travelCard->id,
                ]);
    }
}
}