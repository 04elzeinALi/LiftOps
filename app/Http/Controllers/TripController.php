<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $trips = Trip::with(['schedule', 'bus', 'driver'])->paginate(15);

        return $trips;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'bus_id' => 'required|exists:buses,id',
            'driver_id' => 'required|exists:drivers,id',
            'trip_date' => 'required|date',
            'actual_departure' => 'nullable|date',
            'actual_arrival' => 'nullable|date',
            'status' => 'required|in:scheduled,ongoing,completed,cancelled',
        ]);

        $trip = Trip::create($validated);

        return response()->json($trip, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $trip = Trip::with(['schedule', 'bus', 'driver'])->findOrFail($id);

        return $trip;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'schedule_id' => 'sometimes|required|exists:schedules,id',
            'bus_id' => 'sometimes|required|exists:buses,id',
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'trip_date' => 'sometimes|required|date',
            'actual_departure' => 'nullable|date',
            'actual_arrival' => 'nullable|date',
            'status' => 'sometimes|required|in:scheduled,ongoing,completed,cancelled',
        ]);

        $trip = Trip::findOrFail($id);
        $trip->update($validated);

        return $trip;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $trip = Trip::findOrFail($id);
        $trip->delete();

        return response()->json(['message' => 'Trip deleted successfully']);
    }
}
