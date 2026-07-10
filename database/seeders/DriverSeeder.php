<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $driverUsers = User::where('role', 'driver')->get();

        foreach ($driverUsers as $user) {
            Driver::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}

