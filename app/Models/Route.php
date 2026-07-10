<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['route_name', 'origin','destination','distance_km','estimated_duration','fare'])]

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

}
