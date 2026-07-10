<?php

namespace Database\Factories;

use App\Models\Boarding;
use App\Models\Trip;
use App\Models\Reservation;
use App\Models\Passenger;
use App\Models\TravelCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boarding>
 */
class BoardingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'reservation_id' => Reservation::factory(),
            'passenger_id' => Passenger::factory(),
            'travel_card_id' => TravelCard::factory(),
            'boarded_at' => fake()->dateTime(),
        ];
    }
}
