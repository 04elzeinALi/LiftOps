<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['route_id','departure_time','arrival_time'])]
class Schedule extends Model
{
    use HasFactory;

     public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function scheduleDays()
    {
        return $this->hasMany(ScheduleDay::class);
    }
}
