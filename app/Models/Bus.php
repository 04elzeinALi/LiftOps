<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['plate_number', 'manufacturer', 'model','production_year','capacity','status'])]

class Bus extends Model
{
    use HasFactory;

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * This bus's repair history. Whether any of these are still open is what
     * decides the bus's own status — see MaintenanceController::syncBusStatus().
     */
    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }
}
