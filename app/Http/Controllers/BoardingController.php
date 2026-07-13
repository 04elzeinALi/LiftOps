<?php

namespace App\Http\Controllers;

use App\Models\Boarding;
use App\Models\TravelCard;
use Illuminate\Http\Request;

class BoardingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $boardings = Boarding::with(['trip', 'reservation', 'passenger', 'travelCard'])->paginate(15);

        return $boardings;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'passenger_id' => 'required|exists:passengers,id',
            'travel_card_id' => 'required|exists:travel_cards,id',
            'boarded_at' => 'required|date',
        ]);

        // Business rule: a travel card can only be used while it still has
        // remaining trips. remaining = total_trips - number of boardings so far.
        $travelCard = TravelCard::findOrFail($validated['travel_card_id']);
        $usedTrips = Boarding::where('travel_card_id', $travelCard->id)->count();

        if ($usedTrips >= $travelCard->total_trips) {
            return response()->json([
                'message' => 'This travel card has no remaining trips.',
            ], 422);
        }

        $boarding = Boarding::create($validated);

        return response()->json($boarding, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $boarding = Boarding::with(['trip', 'reservation', 'passenger', 'travelCard'])->findOrFail($id);

        return $boarding;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'trip_id' => 'sometimes|required|exists:trips,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'boarded_at' => 'sometimes|required|date',
        ]);

        $boarding = Boarding::findOrFail($id);
        $boarding->update($validated);

        return $boarding;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $boarding = Boarding::findOrFail($id);
        $boarding->delete();

        return response()->json(['message' => 'Boarding deleted successfully']);
    }
}
