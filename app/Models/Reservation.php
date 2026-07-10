<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['trip_id','passenger_id','travel_card_id','seat_number','pickup_location','reservation_time','status'])]
class Reservation extends Model
{
    use HasFactory;

       public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
        public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }
    public function travelCard()
    {
        return $this->belongsTo(TravelCard::class);
    }
     public function boardings()
    {
        return $this->hasMany(Boarding::class);
    }
}
