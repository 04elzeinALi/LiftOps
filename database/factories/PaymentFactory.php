<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\TravelCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_card_id' => TravelCard::factory(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'payment_method' => fake()->randomElement(['cash', 'credit_card', 'bank_transfer', 'wish']),
            'payment_status' => fake()->randomElement(['unpaid', 'paid', 'failed']),
            'paid_at' => fake()->dateTime(),
        ];
    }
}
