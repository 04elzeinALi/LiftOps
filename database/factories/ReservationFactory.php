<?php

namespace Database\Factories;

use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TravelCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id'=>Passenger::factory(),
            'trip_id'=>Trip::factory(),
            'travel_card_id'=>TravelCard::factory(),
            'seat_number' => fake()->numberBetween(1, 30),
            'pickup_location' => fake()->word(),
            'reservation_time' => fake()->dateTime(),
            'status' => fake()->randomElement(['booked', 'cancelled','completed']),
        ];
    }
}
