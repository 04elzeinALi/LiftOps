<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['schedule_id','bus_id','driver_id','trip_date','actual_departure','actual_arrival','status'])]
class Trip extends Model
{
    use HasFactory;

    protected $appends = ['available_seats'];

       public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
        public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
     public function boardings()
    {
        return $this->hasMany(Boarding::class);
    }
       public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
       public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Seats left on this trip's bus. Not stored — computed from the bus's
     * capacity minus how many people have actually boarded so far.
     */
    protected function availableSeats(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->bus->capacity - $this->boardings()->count()),
        );
    }
}
