<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['trip_id','reservation_id','passenger_id','travel_card_id','from_station_id','to_station_id','boarded_at'])]
class Boarding extends Model
{
    use HasFactory;

    protected $table = 'boarding';
    protected $casts = ['boarded_at' => 'datetime'];

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
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
    public function fromStation()
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }
    public function toStation()
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }
}
