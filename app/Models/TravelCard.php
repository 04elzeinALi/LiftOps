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
     * A card past its expiry date reads as 'expired' whatever the stored
     * column says, so the admin list can't show "Active" on a card nobody can
     * use. The date is the fact; the column is just what someone last typed.
     *
     * 'suspended' is left alone — that's a deliberate administrative action,
     * and hiding it behind "expired" would lose the reason the card was
     * stopped.
     *
     * Read-only shaping: writes still store exactly what was set, and the
     * booking rules in ReservationController/BoardingController check the
     * date independently, so this changes what's *displayed*, never whether
     * a card is honoured.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === 'suspended') {
                    return $value;
                }

                $expiry = $this->attributes['expiry_date'] ?? null;

                return $expiry !== null && $expiry < now()->toDateString() ? 'expired' : $value;
            },
        );
    }

    /**
     * The base fare for one ride on this card — banded on how far the card's
     * segment actually runs along the route, so a Cola-to-Khalde card is
     * cheaper than a Khalde-to-Tyre one.
     *
     * Cards sold before segments existed (or whose stations were since removed
     * from the route) have no segment to measure, and fall back to the route's
     * end-to-end distance instead — the longest reading, never the shortest,
     * so the fallback can't be used to underpay.
     *
     * Every reading resolves through the route's own pricing (see
     * Route::fareForKm), so one route is priced one way no matter which of
     * these paths a card takes.
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

        // A route with no stop sequence has nothing to measure along, so this
        // comes out as 0km and lands in the route's short band. That used to
        // read the flat `fare` column instead, but every route now carries its
        // own bands, so answering from them keeps one route priced one way.
        return $route->fareForKm($route->totalDistanceKm());
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
