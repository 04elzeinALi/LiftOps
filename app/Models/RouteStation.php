<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['route_id','station_id','station_order'])]
class RouteStation extends Model
{
    use HasFactory;

public $timestamps = false;
public $incrementing = false;

       public function route()
    {
        return $this->belongsTo(Route::class);
    }
       public function station()
    {
        return $this->belongsTo(Station::class);
    }

}
