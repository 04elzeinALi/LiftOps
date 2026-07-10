<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['user_id','first_name','last_name','phone_number','status'])]
class Passenger extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function boardings()
    {
        return $this->hasMany(Boarding::class);
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
    public function travelCards()
    {
        return $this->hasMany(TravelCard::class);
    }

}
