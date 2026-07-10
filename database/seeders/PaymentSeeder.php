<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\TravelCard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $travelCards = TravelCard::all();

        foreach ($travelCards as $travelCard) {
            Payment::factory()->create([
            
                'travel_card_id' => $travelCard->id,
            ]);
        }
    }
}
