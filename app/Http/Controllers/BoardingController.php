<?php

namespace App\Http\Controllers;

use App\Models\Boarding;
use App\Models\Driver;
use App\Models\Passenger;
use App\Models\TravelCard;
use App\Models\Trip;
use Illuminate\Http\Request;

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

        $trip = Trip::with('bus')->findOrFail($validated['trip_id']);

        // A passenger can only board using their own profile/card.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        // A driver may record a boarding (e.g. a walk-up cash rider) only
        // for a trip they are actually assigned to drive.
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();

            if (! $ownDriver || $trip->driver_id !== $ownDriver->id) {
                return response()->json([
                    'message' => 'Forbidden: you are not the driver for this trip.',
                ], 403);
            }
        }

        // Business rule: can't board a trip that's no longer running.
        if (in_array($trip->status, ['cancelled', 'completed'])) {
            return response()->json([
                'message' => 'This trip is no longer accepting boardings.',
            ], 422);
        }

        $travelCard = TravelCard::findOrFail($validated['travel_card_id']);

        // Business rule: the travel card must be active and not expired.
        if ($travelCard->status !== 'active' || $travelCard->expiry_date < now()->toDateString()) {
            return response()->json([
                'message' => 'This travel card is not active or has expired.',
            ], 422);
        }

        // Business rule: a travel card can only be used while it still has
        // remaining trips. remaining = total_trips - number of boardings so far.
        $usedTrips = Boarding::where('travel_card_id', $travelCard->id)->count();

        if ($usedTrips >= $travelCard->total_trips) {
            return response()->json([
                'message' => 'This travel card has no remaining trips.',
            ], 422);
        }

        // Business rule: can't board more passengers than the bus can hold.
        $currentBoardings = Boarding::where('trip_id', $trip->id)->count();

        if ($currentBoardings >= $trip->bus->capacity) {
            return response()->json([
                'message' => 'This trip is at full capacity.',
            ], 422);
        }

        $boarding = Boarding::create($validated);

        return response()->json($boarding, 201);
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

        $boarding->update($validated);

        return $boarding;
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
