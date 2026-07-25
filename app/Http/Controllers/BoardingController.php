<?php

namespace App\Http\Controllers;

use App\Models\Boarding;
use App\Models\Driver;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Boarding::with(['trip', 'reservation', 'passenger', 'travelCard']);

        if ($user->role === 'passenger') {
            $query->whereHas('passenger', fn ($q) => $q->where('user_id', $user->id));
        }

        // A driver only sees boardings for trips they are assigned to drive.
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
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'passenger_id' => 'required|exists:passengers,id',
            'travel_card_id' => 'required|exists:travel_cards,id',
            'boarded_at' => 'required|date',
        ]);

        // A passenger can only board using their own profile/card.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        // A driver may record a boarding (e.g. a walk-up cash rider) only
        // for a trip they are actually assigned to drive.
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            $targetTrip = Trip::find($validated['trip_id']);

            if (! $ownDriver || ! $targetTrip || $targetTrip->driver_id !== $ownDriver->id) {
                return response()->json([
                    'message' => 'Forbidden: you are not the driver for this trip.',
                ], 403);
            }
        }

        // Serialize concurrent boardings on this trip (capacity race).
        return DB::transaction(function () use ($validated) {
            $trip = Trip::with('bus')->findOrFail($validated['trip_id']);
            Boarding::where('trip_id', $trip->id)->lockForUpdate()->get();

            $card = TravelCard::findOrFail($validated['travel_card_id']);

            if ($error = $this->boardingRuleError($trip, $card, (int) $validated['passenger_id'], $validated['reservation_id'] ?? null)) {
                return $error;
            }

            $boarding = Boarding::create($validated);

            // A boarding consumes its linked reservation so that seat stops
            // counting as a live booking (keeps reservations + boardings from
            // double-counting the same rider against capacity).
            if (! empty($validated['reservation_id'])) {
                Reservation::where('id', $validated['reservation_id'])
                    ->where('trip_id', $trip->id)
                    ->where('status', 'booked')
                    ->update(['status' => 'completed']);
            }

            return response()->json($boarding, 201);
        });
    }

    /**
     * Returns a 422 response for the first violated boarding rule, or null.
     * Shared by store() and update(). Pass $excludeBoardingId on update so
     * the boarding isn't counted against itself.
     */
    private function boardingRuleError(Trip $trip, TravelCard $card, int $passengerId, $reservationId, ?int $excludeBoardingId = null)
    {
        if (in_array($trip->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'This trip is no longer accepting boardings.'], 422);
        }

        if ($card->status !== 'active' || $card->expiry_date < now()->toDateString()) {
            return response()->json(['message' => 'This travel card is not active or has expired.'], 422);
        }

        if ((int) $card->passenger_id !== $passengerId) {
            return response()->json(['message' => 'This travel card does not belong to this passenger.'], 422);
        }

        // remaining = total_trips − boardings already made on the card.
        $usedTrips = Boarding::where('travel_card_id', $card->id)
            ->when($excludeBoardingId, fn ($q) => $q->where('id', '!=', $excludeBoardingId))
            ->count();

        if ($usedTrips >= $card->total_trips) {
            return response()->json(['message' => 'This travel card has no remaining trips.'], 422);
        }

        // A boarding that converts an existing booked reservation on this
        // trip doesn't add occupancy (that seat is already counted). A
        // walk-up boarding must fit the remaining capacity.
        $convertsReservation = $reservationId
            && Reservation::where('id', $reservationId)
                ->where('trip_id', $trip->id)
                ->where('status', 'booked')
                ->exists();

        if (! $convertsReservation) {
            $occupied = Reservation::where('trip_id', $trip->id)->where('status', 'booked')->count()
                + Boarding::where('trip_id', $trip->id)
                    ->when($excludeBoardingId, fn ($q) => $q->where('id', '!=', $excludeBoardingId))
                    ->count();

            if ($occupied >= $trip->bus->capacity) {
                return response()->json(['message' => 'This trip is at full capacity.'], 422);
            }
        }

        return null;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $boarding = Boarding::with(['trip', 'reservation', 'passenger', 'travelCard'])->findOrFail($id);

        $this->authorizeView($request, $boarding);

        return $boarding;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $boarding = Boarding::findOrFail($id);

        $this->authorizeOwnership($request, $boarding);

        $validated = $request->validate([
            'trip_id' => 'sometimes|required|exists:trips,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'boarded_at' => 'sometimes|required|date',
        ]);

        $effective = array_merge($boarding->only([
            'trip_id', 'travel_card_id', 'passenger_id', 'reservation_id',
        ]), $validated);

        // Re-run the ruleset so the edit path can't move a boarding onto a
        // cancelled/full trip or an invalid/other card (the create path's
        // checks would otherwise be bypassed).
        return DB::transaction(function () use ($boarding, $validated, $effective) {
            $trip = Trip::with('bus')->findOrFail($effective['trip_id']);
            Boarding::where('trip_id', $trip->id)->lockForUpdate()->get();

            $card = TravelCard::findOrFail($effective['travel_card_id']);

            if ($error = $this->boardingRuleError($trip, $card, (int) $effective['passenger_id'], $effective['reservation_id'] ?? null, $boarding->id)) {
                return $error;
            }

            $boarding->update($validated);

            return $boarding;
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $boarding = Boarding::findOrFail($id);

        $this->authorizeOwnership($request, $boarding);

        $boarding->delete();

        return response()->json(['message' => 'Boarding deleted successfully']);
    }

    /**
     * Admins may view any boarding; a passenger may view their own; a
     * driver may view boardings for trips they are driving.
     */
    private function authorizeView(Request $request, Boarding $boarding): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $boarding->passenger?->user_id === $user->id) {
            return;
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            if ($ownDriver && $boarding->trip?->driver_id === $ownDriver->id) {
                return;
            }
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }

    /**
     * Only admins or the owning passenger may update/delete a boarding.
     * Drivers get read + create access (see authorizeView / store), not
     * update/delete.
     */
    private function authorizeOwnership(Request $request, Boarding $boarding): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $boarding->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
