<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteStation;
use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Beirut - Tyre coastal line: every stop the bus calls at, in the
 * order it calls at them, plus the route that strings them together.
 *
 * Safe to re-run. Stations are matched by name (or by the older name they used
 * to have, see the fourth column) so re-running renames rather than duplicates,
 * and coordinates are only written for stations this seeder creates — a
 * coordinate corrected by hand in the admin map picker always wins over the
 * estimates below.
 */
class CoastalLineSeeder extends Seeder
{
    private const ROUTE_NAME = 'B6 - ML2';

    /** [stop name, latitude, longitude, existing station name to reuse] */
    private const STOPS = [
        ['Cola Station', 33.868700, 35.490600, 'Beirut-Cola'],
        ['Embassy of Kuwait Roundabout', 33.857000, 35.489000, null],
        ['Ouzai', 33.843300, 35.490000, null],
        ['Khalde', 33.800300, 35.478300, 'Khaldah'],
        ['Islamic University of Lebanon - Khalde', 33.793000, 35.475000, null],
        ['Naameh', 33.753000, 35.447000, null],
        ['Haret El Naameh - Pedestrian Bridge', 33.745000, 35.443000, null],
        ['Damour', 33.730000, 35.448000, null],
        ['Chouf Bridge Intersection', 33.725000, 35.445000, null],
        ['Damour Bridge', 33.720000, 35.442000, null],
        ['Saadiyat', 33.705000, 35.433000, null],
        ['Jiyeh Municipality', 33.672000, 35.423000, null],
        ['Jiyeh', 33.662000, 35.420000, null],
        ['Barja - Wadi El Zayni Intersection', 33.648000, 35.415000, null],
        ['Jadra Intersection', 33.635000, 35.409000, null],
        ['Wadi El Zayni Intersection', 33.623000, 35.404000, null],
        ['Islamic University of Lebanon - Rmayleh', 33.610861, 35.400222, 'Rmaileh'],
        ['AUST', 33.600000, 35.395000, null],
        ['Sidon Courts', 33.575000, 35.382000, null],
        ['Sidon - Nejmeh Square', 33.561400, 35.371400, 'Saida - Nejmeh Square'],
        ['Al Araby Roundabout', 33.550000, 35.365000, null],
        ['Maghdoucheh Intersection', 33.538000, 35.358000, null],
        ['Raee Hospital', 33.525000, 35.350000, null],
        ['Aaqbiyeh Intersection', 33.500000, 35.338000, null],
        ['Sarafand Intersection', 33.439417, 35.308861, 'Sarafand'],
        ['Loubieh Intersection', 33.425000, 35.301000, null],
        ['Ansariyeh Intersection', 33.410000, 35.295000, null],
        ['Aadloun Intersection', 33.395000, 35.288000, null],
        ['Kfar Badda Intersection', 33.380000, 35.280000, null],
        ['Kharayeb Intersection', 33.365000, 35.273000, null],
        ['El Wastah Intersection', 33.350000, 35.265000, null],
        ['Borj Rahhal Intersection', 33.330000, 35.255000, null],
        ['Borgholiyeh Intersection', 33.310000, 35.240000, null],
        ['Chabriha Intersection', 33.295000, 35.228000, null],
        ['Jabal Amel Hospital', 33.283000, 35.215000, null],
        ['Tyre Roundabout', 33.275000, 35.205000, null],
        ['Tyre - Borj El Chmali Intersection', 33.272000, 35.225000, null],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $stationIds = [];

            foreach (self::STOPS as [$name, $lat, $lng, $replaces]) {
                // Reuse the row this stop already has under an older name, so
                // routes and travel cards pointing at it keep working.
                $station = $replaces
                    ? Station::where('station_name', $replaces)->first()
                    : null;

                $station ??= Station::firstOrNew(['station_name' => $name]);

                // Only seed coordinates for stations that don't exist yet —
                // anything already placed on the map was placed deliberately.
                if (! $station->exists) {
                    $station->latitude = $lat;
                    $station->longitude = $lng;
                }

                $station->station_name = $name;
                $station->save();

                $stationIds[] = $station->id;
            }

            $route = Route::firstOrNew(['route_name' => self::ROUTE_NAME]);
            $route->origin_station_id = $stationIds[0];
            $route->destination_station_id = end($stationIds);
            $route->origin = self::STOPS[0][0];
            $route->destination = self::STOPS[count(self::STOPS) - 1][0];
            $route->estimated_duration = $route->estimated_duration ?: '2h 15m';
            $route->fare = $route->fare ?: 3;
            $route->distance_km = 0; // replaced below, once the stops exist
            $route->save();

            // Rebuild the sequence from scratch so a re-run can't leave stale
            // stops behind from an earlier version of the list.
            RouteStation::where('route_id', $route->id)->delete();

            foreach ($stationIds as $index => $stationId) {
                RouteStation::create([
                    'route_id' => $route->id,
                    'station_id' => $stationId,
                    'station_order' => $index + 1,
                ]);
            }

            $route->distance_km = round($route->fresh()->totalDistanceKm(), 2);
            $route->save();

            $this->command?->info(sprintf(
                'Seeded "%s": %d stops, %s km end to end.',
                $route->route_name,
                count($stationIds),
                $route->distance_km
            ));
        });
    }
}
