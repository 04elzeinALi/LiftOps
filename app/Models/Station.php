<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['station_name', 'latitude','longitude'])]

class Station extends Model
{
    use HasFactory;

     public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }

}
