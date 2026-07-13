<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $maintenance = Maintenance::with('bus')->paginate(15);

        return $maintenance;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'maintenance_type' => 'required|in:oil_change,tire_replacement,brake_inspection,engine_repair,transmission_service,electrical_system_check,suspension_inspection',
            'maintenance_status' => 'required|in:scheduled,in_progress,completed',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'required|date',
            'completed_at' => 'nullable|date',
        ]);

        $maintenance = Maintenance::create($validated);

        return response()->json($maintenance, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $maintenance = Maintenance::with('bus')->findOrFail($id);

        return $maintenance;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'bus_id' => 'sometimes|required|exists:buses,id',
            'maintenance_type' => 'sometimes|required|in:oil_change,tire_replacement,brake_inspection,engine_repair,transmission_service,electrical_system_check,suspension_inspection',
            'maintenance_status' => 'sometimes|required|in:scheduled,in_progress,completed',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'sometimes|required|date',
            'completed_at' => 'nullable|date',
        ]);

        $maintenance = Maintenance::findOrFail($id);
        $maintenance->update($validated);

        return $maintenance;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $maintenance = Maintenance::findOrFail($id);
        $maintenance->delete();

        return response()->json(['message' => 'Maintenance record deleted successfully']);
    }
}
