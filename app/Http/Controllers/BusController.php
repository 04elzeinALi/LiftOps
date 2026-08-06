<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\Maintenance;
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:buses',
            'manufacturer' => 'required|string',
            'model' => 'required|string',
            'production_year' => 'required|integer',
            'capacity' => 'required|integer',
            'status' => 'required|in:in_service,out_of_service,maintenance',
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
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'plate_number' => 'sometimes|required|string|unique:buses,plate_number,' . $id,
            'manufacturer' => 'sometimes|required|string',
            'model' => 'sometimes|required|string',
            'production_year' => 'sometimes|required|integer',
            'capacity' => 'sometimes|required|integer',
            'status' => 'sometimes|required|in:in_service,out_of_service,maintenance',
        ]);

        $bus = Bus::findOrFail($id);

        // A bus that just went INTO maintenance (not one that was already
        // there) gets an open maintenance record automatically, so the admin
        // doesn't have to remember to go create one — they land on the
        // Maintenance page and fill in the type/cost/description from there.
        // Type and scheduled date aren't known yet at this point, so they
        // start as a placeholder ('other', today) rather than guessing.
        $enteringMaintenance = ($validated['status'] ?? null) === 'maintenance' && $bus->status !== 'maintenance';

        $bus->update($validated);

        if ($enteringMaintenance) {
            Maintenance::create([
                'bus_id' => $bus->id,
                'maintenance_type' => 'other',
                'maintenance_status' => 'scheduled',
                'scheduled_at' => now()->toDateString(),
            ]);
        }

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
