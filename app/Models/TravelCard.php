<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['passenger_id','route_id','from_station_id','to_station_id','card_type','purchase_date','expiry_date','total_trips','status'])]
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
    public function fromStation()
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    public function toStation()
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }

    /**
     * The base fare for one ride on this card — banded on how far the card's
     * segment actually runs along the route, so a Cola-to-Khalde card is
     * cheaper than a Khalde-to-Tyre one.
     *
     * Cards sold before segments existed (or whose stations were since removed
     * from the route) have no segment to measure, and fall back to the route's
     * end-to-end fare — the most expensive reading, never the cheapest, so the
     * fallback can't be used to underpay.
     */
    public function baseFare(): float
    {
        $route = $this->route;

        if (! $route) {
            return 0.0;
        }

        // An admin-set price overrides the distance calculation entirely —
        // this is the escape hatch for routes that shouldn't be banded
        // automatically (promos, negotiated rates, corrections).
        if ($route->manual_fare !== null) {
            return (float) $route->manual_fare;
        }

        if ($this->from_station_id && $this->to_station_id) {
            $fare = $route->fareBetweenStations(
                (int) $this->from_station_id,
                (int) $this->to_station_id,
            );

            if ($fare !== null) {
                return $fare;
            }
        }

        // A route with no stop sequence has nothing to measure along, so fall
        // back to its own flat fare column — which is exactly what cards on
        // the older per-segment routes were sold at.
        if ($route->orderedStops()->count() < 2) {
            return (float) $route->fare;
        }

        return $route->totalDistanceKm() < Route::LONG_TRIP_KM
            ? Route::SHORT_TRIP_FARE
            : Route::LONG_TRIP_FARE;
    }

    public function calculatePrice(): float
    {
        $fare = $this->baseFare();

        return match ($this->card_type) {
            'single' => $fare * 1,
            'return' => $fare * 2,
            'weekly' => $fare * 5 * 0.90,
            'monthly' => $fare * 20 * 0.80,
        };
    }

    /**
     * How many trips are left on this card. Not stored — computed from
     * total_trips minus how many are spoken for.
     *
     * A trip counts against the card the moment it's booked, not only once
     * boarded — a passenger who books 3 trips off a 5-trip card expects to
     * see 2 left, not wait until they've physically ridden 3. So "spoken
     * for" is every live-or-completed reservation on this card, plus any
     * walk-up boarding with no reservation behind it. A boarded reservation
     * is excluded from the boarding side of that sum (its own boarding row
     * has reservation_id set) so it isn't counted twice — see
     * BoardingController::store(), which flips the reservation to
     * 'completed' in the same request that creates its boarding.
     *
     * Uses the eager-loaded *_count aliases when present (withCount) to
     * avoid two extra queries per row on list endpoints.
     */
    protected function remainingTrips(): Attribute
    {
        return Attribute::make(
            get: function () {
                $consumedByReservation = $this->consumed_reservations_count
                    ?? $this->reservations()->whereIn('status', ['booked', 'completed'])->count();

                $consumedByWalkUp = $this->walkup_boardings_count
                    ?? $this->boardings()->whereNull('reservation_id')->count();

                return max(0, $this->total_trips - $consumedByReservation - $consumedByWalkUp);
            },
        );
    }

}
