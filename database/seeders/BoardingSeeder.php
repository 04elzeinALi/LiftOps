<?php

namespace Database\Seeders;

use App\Models\Boarding;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoardingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reservations = Reservation::all();

        foreach ($reservations as $reservation) {
            Boarding::factory()->create([
                'passenger_id' => $reservation->passenger_id,
                'trip_id' => $reservation->trip_id,
                'travel_card_id' => $reservation->travel_card_id,
                'reservation_id' => $reservation->id,
            ]);
        }
    }
}
