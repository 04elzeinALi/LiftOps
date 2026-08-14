<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\Maintenance;
use Carbon\Carbon;
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
     * The status a record's own dates imply. Not chosen by hand any more:
     * an admin used to be able to save "completed" with no completion date,
     * or a completion date with the status still on "scheduled", and the
     * record then disagreed with itself about whether the bus was fixed.
     *
     *   completed_at filled            -> completed  (the work is done)
     *   due today or overdue           -> in_progress
     *   due later                      -> scheduled
     *
     * Both of the latter are "open", so the bus stays off the road either
     * way — the distinction is only there to show whether work should have
     * started yet.
     */
    private function deriveStatus(?string $scheduledAt, ?string $completedAt): string
    {
        if (! empty($completedAt)) {
            return 'completed';
        }

        if (empty($scheduledAt)) {
            return 'scheduled';
        }

        // Parsed rather than string-compared: the value can arrive as a bare
        // date from the form or as a full timestamp from the database, and
        // those two don't sort against each other as text.
        return Carbon::parse($scheduledAt)->startOfDay()->lte(now()->startOfDay())
            ? 'in_progress'
            : 'scheduled';
    }

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
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'required|date',
            // You can't finish a job at a time that hasn't happened yet.
            'completed_at' => 'nullable|date|before_or_equal:now',
        ]);

        // Derived, never taken from the request — see deriveStatus().
        $validated['maintenance_status'] = $this->deriveStatus(
            $validated['scheduled_at'] ?? null,
            $validated['completed_at'] ?? null,
        );

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
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'scheduled_at' => 'sometimes|required|date',
            // You can't finish a job at a time that hasn't happened yet.
            'completed_at' => 'nullable|date|before_or_equal:now',
        ]);

        $maintenance = Maintenance::findOrFail($id);
        $previousBusId = (int) $maintenance->bus_id;

        // Derived from the record as it will be after this patch, so clearing
        // a completion date reopens the job (and takes the bus back off the
        // road) exactly as setting one closes it. Read from the raw
        // attributes: these columns aren't cast, so they're plain strings.
        $effectiveScheduled = $validated['scheduled_at']
            ?? $maintenance->getRawOriginal('scheduled_at');
        $effectiveCompleted = array_key_exists('completed_at', $validated)
            ? $validated['completed_at']
            : $maintenance->getRawOriginal('completed_at');

        $validated['maintenance_status'] = $this->deriveStatus($effectiveScheduled, $effectiveCompleted);

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
