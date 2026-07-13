<?php

namespace App\Http\Controllers;

use App\Models\RouteStation;
use Illuminate\Http\Request;

class RouteStationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $routeStations = RouteStation::with(['route', 'station'])->paginate(15);

        return $routeStations;
    }

    /**
     * Store a newly created resource in storage.
     *
     * route_stations uses a composite primary key (route_id, station_id),
     * so there is no single id to look a row up by.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'station_id' => 'required|exists:stations,id',
            'station_order' => 'required|integer',
        ]);

        // Prevent adding the same station to the same route twice.
        $exists = RouteStation::where('route_id', $validated['route_id'])
            ->where('station_id', $validated['station_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This station is already assigned to this route.',
            ], 422);
        }

        $routeStation = RouteStation::create($validated);

        return response()->json($routeStation, 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Identified by the composite key (route_id + station_id) sent in the body.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'station_id' => 'required|exists:stations,id',
        ]);

        $deleted = RouteStation::where('route_id', $validated['route_id'])
            ->where('station_id', $validated['station_id'])
            ->delete();

        if (! $deleted) {
            return response()->json([
                'message' => 'Route station not found.',
            ], 404);
        }

        return response()->json(['message' => 'Route station deleted successfully']);
    }
}
