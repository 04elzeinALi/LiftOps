<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['route_name', 'origin','destination','origin_station_id','destination_station_id','distance_km','estimated_duration','fare','manual_fare','long_trip_km','short_trip_fare','long_trip_fare'])]

class Route extends Model
{
    use HasFactory;

       public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }
   public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
   public function travelCards()
    {
        return $this->hasMany(TravelCard::class);
    }

    public function originStation()
    {
        return $this->belongsTo(Station::class, 'origin_station_id');
    }

    public function destinationStation()
    {
        return $this->belongsTo(Station::class, 'destination_station_id');
    }

    /**
     * Prefers the linked station's current name so a rename shows up
     * everywhere immediately; falls back to the frozen text column for
     * older/unlinked routes (see the backfill migration).
     */
    protected function origin(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->originStation?->station_name ?? $value,
        );
    }

    protected function destination(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->destinationStation?->station_name ?? $value,
        );
    }

    /**
     * Roads curve between stops, so summing straight lines from stop to stop
     * systematically undershoots how far the bus actually drives — measured
     * end to end the coastal line came out ~7% short. This detour index
     * scales the measurement back up to approximate road distance, which
     * matters because the fare is banded on it: without it a Beirut-Saida
     * trip measured 39.4km and slipped under the 40km line as a short trip.
     */
    public const ROAD_FACTOR = 1.1;

    /**
     * What THIS route charges for a ride of $km, before any travel-card
     * multiplier. Resolution order, most specific first:
     *
     *   1. the route's own bands, if it has been given a full set
     *   2. the network-wide defaults in pricing_settings
     *
     * The three band columns are treated as one unit rather than falling
     * back field by field: a route with only its threshold set would
     * otherwise silently mix its own distance line with the global fares,
     * which is a pricing bug nobody would think to look for.
     *
     * A route's manual_fare, where set, skips distance banding altogether
     * and is applied before any of this — see TravelCard::baseFare().
     *
     * Shared by fareBetweenStations() below and TravelCard::baseFare()'s
     * no-segment fallback, so both price a given distance identically.
     */
    public function fareForKm(float $km): float
    {
        $threshold = $this->long_trip_km;
        $short = $this->short_trip_fare;
        $long = $this->long_trip_fare;

        if ($threshold === null || $short === null || $long === null) {
            $settings = PricingSetting::current();
            $threshold = $settings->long_trip_km;
            $short = $settings->short_trip_fare;
            $long = $settings->long_trip_fare;
        }

        return $km < (float) $threshold ? (float) $short : (float) $long;
    }

    /**
     * True when this route prices on its own bands rather than the network
     * defaults — used by the admin UI to show which of the two is in effect.
     */
    public function hasOwnPricing(): bool
    {
        return $this->long_trip_km !== null
            && $this->short_trip_fare !== null
            && $this->long_trip_fare !== null;
    }

    /**
     * What one ride between two stops on this route costs, before any
     * travel-card multiplier. Null if either station isn't on this route.
     */
    public function fareBetweenStations(int $fromStationId, int $toStationId): ?float
    {
        $km = $this->distanceBetweenStations($fromStationId, $toStationId);

        if ($km === null) {
            return null;
        }

        return $this->fareForKm($km);
    }

    /**
     * The stops this route calls at, in call order.
     */
    public function orderedStops()
    {
        return $this->routeStations()->with('station')->orderBy('station_order')->get();
    }

    /**
     * Great-circle distance in km between two coordinates.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * How far apart two stops are *along this route* — the sum of the hops
     * between them in stop order, not the straight line. Following the stop
     * sequence tracks the coastal road far better than one long diagonal,
     * which matters because the fare is banded on this number.
     *
     * Direction-agnostic (Tyre to Cola is the same distance as Cola to Tyre).
     * Returns null if either station isn't a stop on this route.
     */
    public function distanceBetweenStations(int $fromStationId, int $toStationId): ?float
    {
        $stops = $this->orderedStops()->values();

        $from = $stops->search(fn ($stop) => (int) $stop->station_id === $fromStationId);
        $to = $stops->search(fn ($stop) => (int) $stop->station_id === $toStationId);

        if ($from === false || $to === false) {
            return null;
        }

        [$start, $end] = $from <= $to ? [$from, $to] : [$to, $from];

        $km = 0.0;

        for ($i = $start; $i < $end; $i++) {
            $a = $stops[$i]->station;
            $b = $stops[$i + 1]->station;

            if (! $a || ! $b) {
                continue;
            }

            $km += self::haversineKm(
                (float) $a->latitude,
                (float) $a->longitude,
                (float) $b->latitude,
                (float) $b->longitude,
            );
        }

        return $km * self::ROAD_FACTOR;
    }

    /**
     * End-to-end distance along the stop sequence.
     */
    public function totalDistanceKm(): float
    {
        $stops = $this->orderedStops()->values();

        if ($stops->count() < 2) {
            return 0.0;
        }

        return $this->distanceBetweenStations(
            (int) $stops->first()->station_id,
            (int) $stops->last()->station_id,
        ) ?? 0.0;
    }
}
