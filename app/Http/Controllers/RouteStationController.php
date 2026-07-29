<?php

namespace App\Http\Controllers;

use App\Models\RouteStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Rewrite a route's whole stop sequence in one go.
     *
     * The stop order is what the route diagram draws and (later) what the
     * distance between two stops is measured along, so it is replaced
     * wholesale from an ordered list of station ids rather than patched one
     * row at a time — inside a transaction, so a half-applied reorder can't
     * leave two stops sharing a position.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'station_ids' => 'required|array|min:1',
            'station_ids.*' => 'integer|exists:stations,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['station_ids'] as $index => $stationId) {
                RouteStation::where('route_id', $validated['route_id'])
                    ->where('station_id', $stationId)
                    ->update(['station_order' => $index + 1]);
            }
        });

        return RouteStation::with('station')
            ->where('route_id', $validated['route_id'])
            ->orderBy('station_order')
            ->get();
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
            // Optional: omitted means "append to the end of the route", which
            // is what adding a stop from the sequence editor wants.
            'station_order' => 'sometimes|integer',
        ]);

        $validated['station_order'] ??= 1 + (int) RouteStation::where('route_id', $validated['route_id'])->max('station_order');

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
