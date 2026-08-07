<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\Station;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $routes = Route::with(['originStation', 'destinationStation'])->paginate(15);

        return $routes;
    }

    /**
     * Store a newly created resource in storage.
     *
     * origin/destination are picked from Stations, not free-typed — the
     * FK is what keeps them live if a station is later renamed (see
     * Route::origin()/destination() accessors). The text columns are
     * still snapshotted at save time as a fallback for if the station is
     * ever deleted (origin_station_id/destination_station_id null out).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_name' => 'required|string',
            'origin_station_id' => 'required|exists:stations,id',
            'destination_station_id' => 'required|exists:stations,id',
            'distance_km' => 'required|numeric',
            'estimated_duration' => 'required|string',
            'manual_fare' => 'nullable|numeric',
            // This route's own distance bands. All three together or none —
            // see Route::fareForKm(), which won't mix a route's threshold
            // with the network's fares.
            'long_trip_km' => 'nullable|numeric|min:0.1|required_with:short_trip_fare,long_trip_fare',
            'short_trip_fare' => 'nullable|numeric|min:0|required_with:long_trip_km,long_trip_fare',
            'long_trip_fare' => 'nullable|numeric|min:0|required_with:long_trip_km,short_trip_fare',
        ]);

        $validated['origin'] = Station::findOrFail($validated['origin_station_id'])->station_name;
        $validated['destination'] = Station::findOrFail($validated['destination_station_id'])->station_name;

        $route = Route::create($validated);

        return response()->json($route, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // The ordered stop list drives the route diagram (and, later, the
        // distance between any two stops), so it is always loaded in
        // station_order — the order the bus actually calls at them.
        $route = Route::with([
            'originStation',
            'destinationStation',
            'routeStations' => fn ($q) => $q->orderBy('station_order'),
            'routeStations.station',
        ])->findOrFail($id);

        return $route;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'route_name' => 'sometimes|required|string',
            'origin_station_id' => 'sometimes|required|exists:stations,id',
            'destination_station_id' => 'sometimes|required|exists:stations,id',
            'distance_km' => 'sometimes|required|numeric',
            'estimated_duration' => 'sometimes|required|string',
            'manual_fare' => 'sometimes|nullable|numeric',
            'long_trip_km' => 'sometimes|nullable|numeric|min:0.1|required_with:short_trip_fare,long_trip_fare',
            'short_trip_fare' => 'sometimes|nullable|numeric|min:0|required_with:long_trip_km,long_trip_fare',
            'long_trip_fare' => 'sometimes|nullable|numeric|min:0|required_with:long_trip_km,short_trip_fare',
        ]);

        if (isset($validated['origin_station_id'])) {
            $validated['origin'] = Station::findOrFail($validated['origin_station_id'])->station_name;
        }

        if (isset($validated['destination_station_id'])) {
            $validated['destination'] = Station::findOrFail($validated['destination_station_id'])->station_name;
        }

        $route = Route::findOrFail($id);
        $route->update($validated);

        return $route;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $route = Route::findOrFail($id);

        // Block instead of cascade: deleting a route used to cascade through
        // schedules → trips → reservations/boardings, and travel cards →
        // payments — wiping booking and financial history.
        if ($route->schedules()->exists() || $route->travelCards()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this route while schedules or travel cards still reference it. Remove those first.',
            ], 409);
        }

        $route->delete();

        return response()->json(['message' => 'Route deleted successfully']);
    }
}
