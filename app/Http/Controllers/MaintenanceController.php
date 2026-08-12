<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * The maintenance states that mean a bus is off the road. Anything else
     * ('completed') means the work is done and it can go back into service.
     */
    private const OPEN_STATUSES = ['scheduled', 'in_progress'];

    /**
     * Point a bus's status at the truth of its maintenance records: off the
     * road while it has any open record, back in service once none remain.
     *
     * This is the ONLY place bus.status becomes (or stops being)
     * 'maintenance' — BusController refuses that value outright — so the
     * record and the bus can never contradict each other. Recomputed from
     * scratch rather than toggled, so it stays correct no matter which record
     * changed or how many are open at once.
     *
     * A bus taken out_of_service for some other reason is left alone: that's
     * a separate decision from "is it being repaired", and clearing it here
     * would quietly put a retired bus back on the road.
     */
    private function syncBusStatus(?int $busId): void
    {
        if (! $busId) {
            return;
        }

        $bus = Bus::find($busId);
        if (! $bus) {
            return;
        }

        $hasOpenWork = Maintenance::where('bus_id', $busId)
            ->whereIn('maintenance_status', self::OPEN_STATUSES)
            ->exists();

        if ($hasOpenWork) {
            if ($bus->status !== 'maintenance') {
                $bus->update(['status' => 'maintenance']);
            }
            return;
        }

        if ($bus->status === 'maintenance') {
            $bus->update(['status' => 'in_service']);
        }
    }

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
     *
     * Scheduling work on a bus takes it off the road straight away — before
     * this, a bus could have repairs booked and still be assignable to shifts.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'maintenance_type' => 'required|in:oil_change,tire_replacement,brake_inspection,engine_repair,transmission_service,electrical_system_check,suspension_inspection,other',
            'maintenance_status' => 'required|in:scheduled,in_progress,completed',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'required|date',
            'completed_at' => 'nullable|date',
        ]);

        $maintenance = DB::transaction(function () use ($validated) {
            $record = Maintenance::create($validated);
            $this->syncBusStatus((int) $validated['bus_id']);

            return $record;
        });

        return response()->json($maintenance->load('bus'), 201);
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
     *
     * Completing the work is what puts the bus back in service.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'bus_id' => 'sometimes|required|exists:buses,id',
            'maintenance_type' => 'sometimes|required|in:oil_change,tire_replacement,brake_inspection,engine_repair,transmission_service,electrical_system_check,suspension_inspection,other',
            'maintenance_status' => 'sometimes|required|in:scheduled,in_progress,completed',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'sometimes|required|date',
            'completed_at' => 'nullable|date',
        ]);

        $maintenance = Maintenance::findOrFail($id);
        $previousBusId = (int) $maintenance->bus_id;

        DB::transaction(function () use ($maintenance, $validated, $previousBusId) {
            $maintenance->update($validated);

            // Moving a record to a different bus has to settle both: the one
            // it left may now be free, the one it joined may now be off road.
            $this->syncBusStatus($previousBusId);
            $newBusId = (int) $maintenance->fresh()->bus_id;
            if ($newBusId !== $previousBusId) {
                $this->syncBusStatus($newBusId);
            }
        });

        return $maintenance->fresh()->load('bus');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Deleting the last open record frees the bus — otherwise it would stay
     * stranded in maintenance with nothing left to explain why.
     */
    public function destroy(string $id)
    {
        $maintenance = Maintenance::findOrFail($id);
        $busId = (int) $maintenance->bus_id;

        DB::transaction(function () use ($maintenance, $busId) {
            $maintenance->delete();
            $this->syncBusStatus($busId);
        });

        return response()->json(['message' => 'Maintenance record deleted successfully']);
    }
}
