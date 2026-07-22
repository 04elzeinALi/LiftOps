<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\TravelCard;
use Illuminate\Http\Request;

class TravelCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = TravelCard::with(['passenger', 'route']);

        // A passenger only sees their own cards. Drivers see everything
        // (no trip_id on this table to scope by) so they can check a
        // walk-up rider's card before letting them board.
        if ($user->role === 'passenger') {
            $query->whereHas('passenger', fn ($q) => $q->where('user_id', $user->id));
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
            'passenger_id' => 'required|exists:passengers,id',
            'route_id' => 'required|exists:routes,id',
            'card_type' => 'required|in:single,return,weekly,monthly',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'total_trips' => 'required|integer',
            'status' => 'required|in:active,expired,suspended',
        ]);

        // A passenger can only buy a travel card tied to their own profile.
        // Drivers may create one for any passenger — this covers a walk-up
        // rider paying cash directly to the driver for a single-trip card.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        $travelCard = TravelCard::create($validated);

        return response()->json($travelCard, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $travelCard = TravelCard::with(['passenger', 'route'])->findOrFail($id);

        $this->authorizeView($request, $travelCard);

        return $travelCard;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $travelCard = TravelCard::findOrFail($id);

        $this->authorizeWrite($request, $travelCard);

        $validated = $request->validate([
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'route_id' => 'sometimes|required|exists:routes,id',
            'card_type' => 'sometimes|required|in:single,return,weekly,monthly',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'sometimes|required|date',
            'total_trips' => 'sometimes|required|integer',
            'status' => 'sometimes|required|in:active,expired,suspended',
        ]);

        $travelCard->update($validated);

        return $travelCard;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $travelCard = TravelCard::findOrFail($id);

        $this->authorizeWrite($request, $travelCard);

        $travelCard->delete();

        return response()->json(['message' => 'Travel card deleted successfully']);
    }

    /**
     * Admins and drivers may view any card; a passenger may only view a
     * card tied to their own Passenger profile.
     */
    private function authorizeView(Request $request, TravelCard $travelCard): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'driver') {
            return;
        }

        if ($user->role === 'passenger' && $travelCard->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }

    /**
     * Only admins, or the owning passenger, may update/delete a card.
     * Drivers can create cards (walk-up sales) but not edit existing ones.
     */
    private function authorizeWrite(Request $request, TravelCard $travelCard): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $travelCard->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
