<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['route_name', 'origin','destination','origin_station_id','destination_station_id','distance_km','estimated_duration','fare'])]

class Route extends Model
{
    use HasFactory;

       public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }
   public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
   public function travelCards()
    {
        return $this->hasMany(TravelCard::class);
    }

    public function originStation()
    {
        return $this->belongsTo(Station::class, 'origin_station_id');
    }

    public function destinationStation()
    {
        return $this->belongsTo(Station::class, 'destination_station_id');
    }

    /**
     * Prefers the linked station's current name so a rename shows up
     * everywhere immediately; falls back to the frozen text column for
     * older/unlinked routes (see the backfill migration).
     */
    protected function origin(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->originStation?->station_name ?? $value,
        );
    }

    protected function destination(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->destinationStation?->station_name ?? $value,
        );
    }
}
