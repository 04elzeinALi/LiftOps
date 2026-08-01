<?php

namespace App\Http\Controllers;

use App\Models\Boarding;
use App\Models\Driver;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Reservation::with(['passenger', 'trip.schedule.route', 'trip.shift.route', 'travelCard.fromStation', 'travelCard.toStation']);

        if ($user->role === 'passenger') {
            $query->whereHas('passenger', fn ($q) => $q->where('user_id', $user->id));
        }

        // A driver only sees reservations for trips they are assigned to
        // drive — they need this to know who to expect on their bus.
        if ($user->role === 'driver') {
            $query->whereHas('trip', function ($q) use ($user) {
                $q->whereHas('driver', fn ($dq) => $dq->where('user_id', $user->id));
            });
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->query('trip_id'));
        }

        return $query->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Reservations are a passenger self-service action — drivers do not
     * create these (a walk-up rider is boarded directly, see Boarding).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'driver') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'passenger_id' => 'required|exists:passengers,id',
            'trip_id' => 'required|exists:trips,id',
            'travel_card_id' => 'required|exists:travel_cards,id',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'required|date',
            'status' => 'required|in:booked,cancelled,completed',
        ]);

        // A passenger can only book a reservation for themselves.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        // A new reservation is always a live booking.
        $validated['status'] = 'booked';

        // Serialize concurrent bookings on this trip so two requests can't
        // both pass the capacity/seat checks and both insert (overbooking /
        // double-booked seat race).
        return DB::transaction(function () use ($validated) {
            $trip = Trip::with(['bus', 'schedule.route', 'shift.route'])->findOrFail($validated['trip_id']);
            Reservation::where('trip_id', $trip->id)->lockForUpdate()->get();

            $card = TravelCard::findOrFail($validated['travel_card_id']);

            if ($error = $this->reservationRuleError($trip, $card, (int) $validated['passenger_id'])) {
                return $error;
            }

            $reservation = Reservation::create($validated);

            return response()->json($reservation, 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $reservation = Reservation::with(['passenger', 'trip.schedule.route', 'trip.shift.route', 'travelCard.fromStation', 'travelCard.toStation'])->findOrFail($id);

        $this->authorizeView($request, $reservation);

        return $reservation;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);

        $this->authorizeOwnership($request, $reservation);

        $validated = $request->validate([
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:booked,cancelled,completed',
        ]);

        // Effective values after the patch is applied.
        $effective = array_merge($reservation->only([
            'passenger_id', 'trip_id', 'travel_card_id', 'status',
        ]), $validated);

        // Re-run the full booking ruleset whenever the reservation would
        // remain (or become) a live booking — otherwise the edit path is a
        // hole around every rule the create path enforces (moving to a full
        // /cancelled trip, a taken seat, an expired/wrong/unpaid/other card).
        if ($effective['status'] === 'booked') {
            return DB::transaction(function () use ($reservation, $validated, $effective) {
                $trip = Trip::with(['bus', 'schedule.route', 'shift.route'])->findOrFail($effective['trip_id']);
                Reservation::where('trip_id', $trip->id)->lockForUpdate()->get();

                $card = TravelCard::findOrFail($effective['travel_card_id']);

                if ($error = $this->reservationRuleError($trip, $card, (int) $effective['passenger_id'], $reservation->id)) {
                    return $error;
                }

                $reservation->update($validated);

                return $reservation;
            });
        }

        $reservation->update($validated);

        return $reservation;
    }

    /**
     * Returns a 422 response describing the first violated booking rule, or
     * null if the reservation is allowed. Shared by store() and update() so
     * both paths enforce the same invariants. Pass $excludeReservationId
     * when updating so the reservation isn't counted against itself.
     */
    private function reservationRuleError(Trip $trip, TravelCard $card, int $passengerId, ?int $excludeReservationId = null)
    {
        if (in_array($trip->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'This trip is no longer accepting reservations.'], 422);
        }

        if ($card->status !== 'active' || $card->expiry_date < now()->toDateString()) {
            return response()->json(['message' => 'This travel card is not active or has expired.'], 422);
        }

        // A trip's route comes from its shift now, falling back to the old
        // schedule for trips that predate shifts (see Trip::getRouteAttribute).
        // Reading ->schedule->route_id directly crashed every booking once
        // trips became shift legs, since a leg's schedule_id is null.
        if ($card->route_id !== $trip->route?->id) {
            return response()->json(['message' => 'This travel card is not valid for this route.'], 422);
        }

        // The card must belong to the passenger doing the booking — you can't
        // ride on someone else's card.
        if ((int) $card->passenger_id !== $passengerId) {
            return response()->json(['message' => 'This travel card does not belong to this passenger.'], 422);
        }

        $hasPaid = Payment::where('travel_card_id', $card->id)
            ->where('payment_status', 'paid')
            ->exists();

        if (! $hasPaid) {
            return response()->json(['message' => 'Payment required: this travel card has no confirmed payment yet.'], 422);
        }

        // A card can't hold more live (booked) reservations than it has
        // remaining trips — otherwise a 1-trip card could reserve many seats.
        $bookedOnCard = Reservation::where('travel_card_id', $card->id)
            ->where('status', 'booked')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->count();

        if ($bookedOnCard >= $card->remaining_trips) {
            return response()->json(['message' => 'This travel card has no remaining trips.'], 422);
        }

        // Capacity counts booked reservations AND boardings together — a
        // reserved rider who boards has their reservation marked completed
        // (see BoardingController), so the two sets never double-count.
        $occupied = Reservation::where('trip_id', $trip->id)
            ->where('status', 'booked')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->count()
            + Boarding::where('trip_id', $trip->id)->count();

        if ($occupied >= $trip->bus->capacity) {
            return response()->json(['message' => 'This trip is fully booked.'], 422);
        }

        return null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);

        $this->authorizeOwnership($request, $reservation);

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully']);
    }

    /**
     * Admins may view any reservation; a passenger may view their own;
     * a driver may view reservations for trips they are driving.
     */
    private function authorizeView(Request $request, Reservation $reservation): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $reservation->passenger?->user_id === $user->id) {
            return;
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            if ($ownDriver && $reservation->trip?->driver_id === $ownDriver->id) {
                return;
            }
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }

    /**
     * Only admins or the owning passenger may update/delete a reservation.
     * Drivers get read access only (see authorizeView), not write.
     */
    private function authorizeOwnership(Request $request, Reservation $reservation): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $reservation->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
