<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $trips = Trip::with(['schedule.route', 'bus', 'driver'])->paginate(15);

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

        // Business rule: only an in-service bus and an active driver can
        // be assigned to a trip.
        $bus = Bus::findOrFail($validated['bus_id']);
        if ($bus->status !== 'in_service') {
            return response()->json(['message' => 'This bus is not in service.'], 422);
        }

        $driver = Driver::findOrFail($validated['driver_id']);
        if ($driver->status !== 'active') {
            return response()->json(['message' => 'This driver is not active.'], 422);
        }

        $trip = Trip::create($validated);

        return response()->json($trip, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $trip = Trip::with(['schedule.route', 'bus', 'driver'])->findOrFail($id);

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

        if (isset($validated['bus_id'])) {
            $bus = Bus::findOrFail($validated['bus_id']);
            if ($bus->status !== 'in_service') {
                return response()->json(['message' => 'This bus is not in service.'], 422);
            }
        }

        if (isset($validated['driver_id'])) {
            $driver = Driver::findOrFail($validated['driver_id']);
            if ($driver->status !== 'active') {
                return response()->json(['message' => 'This driver is not active.'], 422);
            }
        }

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
