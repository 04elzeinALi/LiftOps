<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Reservation::with(['passenger', 'trip', 'travelCard']);

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
            'seat_number' => 'required|integer',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'required|date',
            'status' => 'required|in:booked,cancelled,completed',
        ]);

        // A passenger can only book a reservation for themselves.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        $trip = Trip::with(['bus', 'schedule.route'])->findOrFail($validated['trip_id']);

        // Business rule: can't reserve a seat on a trip that's no longer running.
        if (in_array($trip->status, ['cancelled', 'completed'])) {
            return response()->json([
                'message' => 'This trip is no longer accepting reservations.',
            ], 422);
        }

        $travelCard = TravelCard::findOrFail($validated['travel_card_id']);

        // Business rule: the travel card must be active and not expired.
        if ($travelCard->status !== 'active' || $travelCard->expiry_date < now()->toDateString()) {
            return response()->json([
                'message' => 'This travel card is not active or has expired.',
            ], 422);
        }

        // Business rule: the travel card must be valid for this trip's route.
        if ($travelCard->route_id !== $trip->schedule->route_id) {
            return response()->json([
                'message' => 'This travel card is not valid for this route.',
            ], 422);
        }

        // Business rule: the travel card must have a confirmed (paid) payment
        // before a reservation can be booked.
        $hasPaid = Payment::where('travel_card_id', $validated['travel_card_id'])
            ->where('payment_status', 'paid')
            ->exists();

        if (! $hasPaid) {
            return response()->json([
                'message' => 'Payment required: this travel card has no confirmed payment yet.',
            ], 422);
        }

        // Business rule: can't overbook the bus.
        $bookedSeats = Reservation::where('trip_id', $trip->id)
            ->where('status', 'booked')
            ->count();

        if ($bookedSeats >= $trip->bus->capacity) {
            return response()->json([
                'message' => 'This trip is fully booked.',
            ], 422);
        }

        // Business rule: no two passengers can hold the same seat on the same trip.
        $seatTaken = Reservation::where('trip_id', $trip->id)
            ->where('seat_number', $validated['seat_number'])
            ->where('status', 'booked')
            ->exists();

        if ($seatTaken) {
            return response()->json([
                'message' => 'This seat is already reserved on this trip.',
            ], 422);
        }

        $reservation = Reservation::create($validated);

        return response()->json($reservation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $reservation = Reservation::with(['passenger', 'trip', 'travelCard'])->findOrFail($id);

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
            'seat_number' => 'sometimes|required|integer',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:booked,cancelled,completed',
        ]);

        $reservation->update($validated);

        return $reservation;
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
