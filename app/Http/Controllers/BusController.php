<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use Illuminate\Http\Request;

class BusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $buses = Bus::paginate(15);

        return $buses;
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Store a newly created resource in storage.
     *
     * 'maintenance' is not accepted here — see update() for why.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:buses',
            'manufacturer' => 'required|string',
            'model' => 'required|string',
            'production_year' => 'required|integer',
            'capacity' => 'required|integer',
            'status' => 'required|in:in_service,out_of_service',
        ]);

        $bus = Bus::create($validated);

        return response()->json($bus, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $bus = Bus::findOrFail($id);

        return $bus;
    }

    /**
     * Update the specified resource in storage.
     *
     * 'maintenance' is deliberately not an accepted status here. A bus is put
     * into and taken out of maintenance by its maintenance records alone (see
     * MaintenanceController), so there is exactly one writer of that fact and
     * the two can't drift apart — previously a bus could be flipped out of
     * maintenance here while an open repair record still said otherwise, or
     * left stranded in maintenance after its repair was completed.
     *
     * A bus already under maintenance keeps that status: its other fields stay
     * editable, and a request that omits `status` leaves it untouched.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'plate_number' => 'sometimes|required|string|unique:buses,plate_number,' . $id,
            'manufacturer' => 'sometimes|required|string',
            'model' => 'sometimes|required|string',
            'production_year' => 'sometimes|required|integer',
            'capacity' => 'sometimes|required|integer',
            'status' => 'sometimes|required|in:in_service,out_of_service',
        ]);

        $bus = Bus::findOrFail($id);

        // Changing a under-maintenance bus back to in/out of service has to go
        // through its maintenance record, or the record would be left open
        // describing a bus that is no longer being repaired.
        if ($bus->status === 'maintenance' && isset($validated['status'])) {
            return response()->json([
                'message' => 'This bus is under maintenance. Close its maintenance record to change its status.',
            ], 422);
        }

        $bus->update($validated);

        return $bus;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bus = Bus::findOrFail($id);
        $bus->delete();

        return response()->json(['message' => 'Bus deleted successfully']);
    }
}
