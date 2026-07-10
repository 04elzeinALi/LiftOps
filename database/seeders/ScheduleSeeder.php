<?php

namespace Database\Seeders;

use App\Models\Route;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    $routes = Route::all();

        foreach ($routes as $route) {
            Schedule::factory()->count(2)->create([
                'route_id' => $route->id,
            ]);
        }
    }
}
