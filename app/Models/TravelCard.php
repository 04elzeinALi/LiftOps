<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['passenger_id','route_id','card_type','purchase_date','expiry_date','total_trips','status'])]
class TravelCard extends Model
{
    use HasFactory;

        public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }
        public function route()
    {
        return $this->belongsTo(Route::class);
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
     public function boardings()
    {
        return $this->hasMany(Boarding::class);
    }
     public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
