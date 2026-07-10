<?php

namespace Database\Seeders;

use App\Models\Passenger;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PassengerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $passengerUsers = User::where('role', 'passenger')->get();

        foreach ($passengerUsers as $user) {
            Passenger::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
