<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['route_name', 'origin','destination','origin_station_id','destination_station_id','distance_km','estimated_duration','fare','manual_fare'])]

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

    /** Trips at or beyond this many km cost the long-trip fare. */
    public const LONG_TRIP_KM = 40;
    public const SHORT_TRIP_FARE = 2.0;
    public const LONG_TRIP_FARE = 3.0;

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

        return $km < self::LONG_TRIP_KM ? self::SHORT_TRIP_FARE : self::LONG_TRIP_FARE;
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
