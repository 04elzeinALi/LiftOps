<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bus_id' => Bus::factory(),
            'maintenance_type' => fake()->randomElement(['oil_change', 'tire_replacement', 'brake_inspection', 'engine_repair', 'transmission_service', 'electrical_system_check', 'suspension_inspection']),
            'maintenance_status' => fake()->randomElement(['scheduled', 'in_progress', 'completed']),
            'description' => fake()->sentence(),
            'cost' => fake()->randomFloat(2, 100, 5000),
            'scheduled_at' => fake()->date(),
            'completed_at' => fake()->dateTime(),
        ];
    }
}
