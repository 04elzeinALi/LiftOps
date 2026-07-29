<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['schedule_id','shift_id','round_number','direction','bus_id','driver_id','trip_date','departure_time','arrival_time','actual_departure','actual_arrival','status'])]
class Trip extends Model
{
    use HasFactory;

    protected $appends = ['available_seats'];

       public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * A trip's route comes from its shift now. Trips created under the old
     * timetable have no shift, so they still read it off their schedule.
     */
    public function getRouteAttribute()
    {
        return $this->shift?->route ?? $this->schedule?->route;
    }

    /**
     * Times live on the trip itself now. Trips predating shifts kept theirs on
     * the schedule, so fall back to that rather than showing nothing.
     */
    protected function departureTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? $this->schedule?->departure_time,
        );
    }

    protected function arrivalTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? $this->schedule?->arrival_time,
        );
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
     * Seats left on this trip's bus. Not stored — capacity minus occupancy,
     * where occupancy = booked reservations + boardings (a rider who boards
     * has their reservation marked completed, so the two sets never
     * double-count). Matches what Reservation/Boarding controllers enforce.
     * Uses eager-loaded *_count values when present (withCount) to avoid an
     * N+1 per row on list endpoints.
     */
    protected function availableSeats(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->bus->capacity
                - ($this->booked_reservations_count ?? $this->reservations()->where('status', 'booked')->count())
                - ($this->boardings_count ?? $this->boardings()->count())),
        );
    }
}
