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
    public function index(Request $request)
    {
        $query = Trip::with(['schedule.route.originStation', 'schedule.route.destinationStation', 'shift.route.originStation', 'shift.route.destinationStation', 'bus', 'driver'])
            ->withCount(['reservations as booked_reservations_count' => fn ($q) => $q->where('status', 'booked'), 'boardings']);

        if ($request->user()->role === 'driver') {
            $query->whereHas('driver', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        if ($request->filled('trip_date')) {
            $query->whereDate('trip_date', $request->query('trip_date'));
        }

        // Everything from this date onward. A passenger browsing trips to book
        // has no use for ones that already ran, and the caller passes its own
        // local date rather than the server's — "today" is the passenger's
        // today, not the server timezone's.
        if ($request->filled('from_date')) {
            $query->whereDate('trip_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Group a shift's segments together and in running order, so the
        // admin list reads as "this shift, then its segments" rather than an
        // interleaved pile of every trip. Newest day first, since that's what
        // an admin is usually looking for.
        $query->orderByDesc('trip_date')
            ->orderBy('shift_id')
            ->orderBy('round_number')
            // outbound before inbound within a round: 'outbound' sorts after
            // 'inbound' alphabetically, so order explicitly rather than by name.
            ->orderByRaw("FIELD(direction, 'outbound', 'inbound')")
            ->orderBy('departure_time');

        return $query->paginate(15);
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
        ]);

        // A brand-new trip is always 'scheduled'. Status transitions
        // (ongoing/completed/cancelled/emergency) happen later via update(),
        // so it isn't chosen at creation time.
        $validated['status'] = 'scheduled';

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
        $trip = Trip::with(['schedule.route.originStation', 'schedule.route.destinationStation', 'shift.route.originStation', 'shift.route.destinationStation', 'bus', 'driver'])
            ->withCount(['reservations as booked_reservations_count' => fn ($q) => $q->where('status', 'booked'), 'boardings'])
            ->findOrFail($id);

        return $trip;
    }

    /**
     * Update the specified resource in storage.
     *
     * Admins may update any field. A driver may update only the `status`
     * of a trip they are assigned to (start/end their own trip). Passengers
     * have no access here.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $trip = Trip::findOrFail($id);

        if ($user->role === 'passenger') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();

            if (! $ownDriver || $trip->driver_id !== $ownDriver->id) {
                return response()->json([
                    'message' => 'Forbidden: you are not the driver for this trip.',
                ], 403);
            }

            $validated = $request->validate([
                // 'emergency' lets a driver flag a trip they're on — not a
                // normal lifecycle transition, but a distress signal an
                // admin should notice.
                'status' => 'required|in:scheduled,ongoing,completed,cancelled,emergency',
            ]);

            // Starting and ending a trip is the only moment anyone actually
            // knows when it ran, so record it here. Before this, the actual
            // times were only ever filled in by an admin typing them into the
            // trip form, which meant there was no honest basis for comparing
            // a trip against its timetable.
            //
            // Only stamped when not already set: an admin correcting a time
            // afterwards shouldn't be overwritten if the driver re-saves, and
            // re-entering a status shouldn't move the clock.
            if ($validated['status'] === 'ongoing' && ! $trip->actual_departure) {
                $validated['actual_departure'] = now();
            }

            if ($validated['status'] === 'completed' && ! $trip->actual_arrival) {
                $validated['actual_arrival'] = now();
            }

            $trip->update($validated);

            return $trip;
        }

        $validated = $request->validate([
            'schedule_id' => 'sometimes|required|exists:schedules,id',
            'bus_id' => 'sometimes|required|exists:buses,id',
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'trip_date' => 'sometimes|required|date',
            // A leg's own planned times. Editable so an admin can nudge a
            // single leg without reshaping the whole shift around it — note a
            // later shift edit re-derives these from the shift's window.
            'departure_time' => 'sometimes|nullable|date_format:H:i',
            'arrival_time' => 'sometimes|nullable|date_format:H:i',
            'actual_departure' => 'nullable|date',
            'actual_arrival' => 'nullable|date',
            'status' => 'sometimes|required|in:scheduled,ongoing,completed,cancelled,emergency',
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
