<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    /**
     * Calls off every segment of a shift, and every live booking on them.
     *
     * A cancelled shift used to leave its segments 'scheduled', which meant
     * passengers could still see and book a bus that was never going to run.
     * Cancelling the reservations is also what gives each passenger their trip
     * back: TravelCard::remainingTrips() counts only 'booked' and 'completed'
     * reservations, so a cancelled one stops being charged against the card.
     *
     * Everyone affected is told, because the alternative is a passenger
     * standing at a stop waiting for a bus that isn't coming. Segments that
     * already ran (completed) are left alone — they happened.
     */
    private function cancelShiftWork(Shift $shift): void
    {
        $routeName = $shift->route?->route_name ?? 'your route';
        $shiftDate = $shift->shift_date?->format('d/m/Y') ?? '';

        $trips = $shift->trips()->whereNotIn('status', ['completed', 'cancelled'])->get();

        foreach ($trips as $trip) {
            $reservations = Reservation::with('passenger')
                ->where('trip_id', $trip->id)
                ->where('status', 'booked')
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update(['status' => 'cancelled']);

                $userId = $reservation->passenger?->user_id;
                if ($userId) {
                    $departure = $trip->departure_time ? substr($trip->departure_time, 0, 5) : '';
                    Notification::send(
                        $userId,
                        'trip_cancelled',
                        'Your trip was cancelled',
                        trim("The {$departure} trip on {$routeName} on {$shiftDate} has been cancelled. "
                            . 'Your booking is cancelled and the trip has been returned to your travel card.')
                    );
                }
            }

            $trip->update(['status' => 'cancelled']);
        }

        // The driver finds out from the same place their shifts come from.
        $driverUserId = $shift->driver?->user_id;
        if ($driverUserId) {
            Notification::send(
                $driverUserId,
                'shift_cancelled',
                'Your shift was cancelled',
                trim("Your shift on {$routeName} on {$shiftDate} has been cancelled. "
                    . 'Its segments are off, and anyone who had booked has been told.')
            );
        }
    }

    /**
     * Display a listing of the resource.
     *
     * Admins see every shift; a driver only sees their own, which is what the
     * driver's day-by-day view reads.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Shift::with(['driver', 'bus', 'route'])
            ->withCount('trips');

        if ($user->role === 'driver') {
            $query->whereHas('driver', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('shift_date')) {
            $query->whereDate('shift_date', $request->query('shift_date'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->query('driver_id'));
        }

        if ($request->filled('route_id')) {
            $query->where('route_id', $request->query('route_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $query->orderByDesc('shift_date')->orderBy('start_time')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'shift_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'rounds' => 'sometimes|integer|min:1|max:6',
        ]);

        if ($error = $this->assignmentError($validated)) {
            return $error;
        }

        // A shift always starts scheduled; it moves through its lifecycle via
        // update(), same as a trip.
        $validated['status'] = 'scheduled';
        $validated['rounds'] ??= 2;

        $shift = DB::transaction(function () use ($validated) {
            $shift = Shift::create($validated);
            $shift->syncTrips();

            return $shift;
        });

        return response()->json($shift->load(['driver', 'bus', 'route', 'trips']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $shift = Shift::with(['driver', 'bus', 'route', 'trips'])->findOrFail($id);

        $this->authorizeView($request, $shift);

        return $shift;
    }

    /**
     * Update the specified resource in storage.
     *
     * Admins may change anything. A driver may only move their own shift's
     * status (clocking on, finishing, flagging an emergency).
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $shift = Shift::findOrFail($id);

        if ($user->role === 'passenger') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();

            if (! $ownDriver || $shift->driver_id !== $ownDriver->id) {
                return response()->json([
                    'message' => 'Forbidden: this is not your shift.',
                ], 403);
            }

            $validated = $request->validate([
                'status' => 'required|in:scheduled,ongoing,completed,cancelled,emergency',
            ]);

            $shift->update($validated);

            return $shift;
        }

        $validated = $request->validate([
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'bus_id' => 'sometimes|required|exists:buses,id',
            'route_id' => 'sometimes|required|exists:routes,id',
            'shift_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'rounds' => 'sometimes|integer|min:1|max:6',
            'status' => 'sometimes|required|in:scheduled,ongoing,completed,cancelled,emergency',
        ]);

        $effective = array_merge(
            $shift->only(['driver_id', 'bus_id', 'route_id', 'shift_date', 'start_time', 'end_time']),
            $validated,
        );

        if ($error = $this->assignmentError($effective, $shift->id)) {
            return $error;
        }

        $newStatus = $validated['status'] ?? null;
        // Only the transition INTO cancelled calls the work off — re-saving an
        // already-cancelled shift mustn't fire a second round of notifications
        // at people who were told days ago.
        $isCancelling = $newStatus === 'cancelled' && $shift->status !== 'cancelled';
        // And the way back out puts the segments on again. Bookings are NOT
        // restored: those passengers were told it was off and had their trip
        // refunded to their card, so they rebook if they still want the seat.
        $isReinstating = $newStatus !== null && $newStatus !== 'cancelled' && $shift->status === 'cancelled';

        DB::transaction(function () use ($shift, $validated, $isCancelling, $isReinstating) {
            $shift->update($validated);

            if ($isCancelling) {
                // Cancel the segments and their bookings BEFORE syncing, so
                // syncTrips() sees the final state and doesn't reshape trips
                // that are now called off.
                $this->cancelShiftWork($shift->fresh()->load(['route', 'driver']));
                return;
            }

            if ($isReinstating) {
                $shift->trips()->where('status', 'cancelled')->update(['status' => 'scheduled']);
            }

            // The hours or round count may have moved, so bring the segments
            // back in line with the shift they belong to.
            $shift->refresh()->syncTrips();
        });

        return $shift->load(['driver', 'bus', 'route', 'trips']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Refused while anything is booked on the shift's legs — cancelling the
     * shift is the right move there, so the reservations stay auditable.
     */
    public function destroy(string $id)
    {
        $shift = Shift::with('trips')->findOrFail($id);

        $booked = $shift->trips()
            ->where(fn ($q) => $q->has('reservations')->orHas('boardings'))
            ->exists();

        if ($booked) {
            return response()->json([
                'message' => 'This shift has trips with reservations or boardings. Cancel it instead of deleting it.',
            ], 409);
        }

        $shift->delete();

        return response()->json(['message' => 'Shift deleted successfully']);
    }

    /**
     * Shared checks for assigning a driver and bus to a shift: the bus has to
     * be in service, the driver active, and neither already committed to
     * another shift that day.
     */
    private function assignmentError(array $data, ?int $ignoreShiftId = null)
    {
        $bus = Bus::find($data['bus_id']);
        if (! $bus || $bus->status !== 'in_service') {
            return response()->json(['message' => 'This bus is not in service.'], 422);
        }

        $driver = Driver::find($data['driver_id']);
        if (! $driver || $driver->status !== 'active') {
            return response()->json(['message' => 'This driver is not active.'], 422);
        }

        $date = $data['shift_date'] instanceof \DateTimeInterface
            ? $data['shift_date']->format('Y-m-d')
            : (string) $data['shift_date'];

        $clash = fn (string $column, $value) => Shift::whereDate('shift_date', $date)
            ->where($column, $value)
            ->when($ignoreShiftId, fn ($q) => $q->where('id', '!=', $ignoreShiftId))
            ->exists();

        if ($clash('driver_id', $data['driver_id'])) {
            return response()->json([
                'message' => 'This driver already has a shift on that day.',
            ], 422);
        }

        if ($clash('bus_id', $data['bus_id'])) {
            return response()->json([
                'message' => 'This bus is already assigned to a shift on that day.',
            ], 422);
        }

        return null;
    }

    /**
     * Admins see any shift; a driver only their own.
     */
    private function authorizeView(Request $request, Shift $shift): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            if ($ownDriver && $shift->driver_id === $ownDriver->id) {
                return;
            }
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
