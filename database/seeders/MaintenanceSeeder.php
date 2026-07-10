<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Maintenance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buses = Bus::all();

        foreach ($buses as $bus) {
            Maintenance::factory()->create([
                'bus_id' => $bus->id,
            ]);
            

    }
}

}