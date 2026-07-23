<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['passenger_id','route_id','card_type','purchase_date','expiry_date','total_trips','status'])]
class TravelCard extends Model
{
    use HasFactory;

    protected $appends = ['remaining_trips'];

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
    public function calculatePrice(): float
{
    $fare = $this->route->fare;

    return match ($this->card_type) {
        'single' => $fare * 1,
        'return' => $fare * 2,
        'weekly' => $fare * 5 * 0.90,
        'monthly' => $fare * 20 * 0.80,
    };
}

    /**
     * How many trips are left on this card. Not stored — computed from
     * total_trips minus how many times it has actually been used to board.
     * Uses the eager-loaded boardings_count when present (withCount) to
     * avoid an N+1 query per row on list endpoints.
     */
    protected function remainingTrips(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->total_trips - ($this->boardings_count ?? $this->boardings()->count())),
        );
    }

}
