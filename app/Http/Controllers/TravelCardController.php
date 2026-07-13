<?php

namespace App\Http\Controllers;

use App\Models\TravelCard;
use Illuminate\Http\Request;

class TravelCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $travelCards = TravelCard::with(['passenger', 'route'])->paginate(15);

        return $travelCards;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'passenger_id' => 'required|exists:passengers,id',
            'route_id' => 'required|exists:routes,id',
            'card_type' => 'required|in:single,return,weekly,monthly',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'total_trips' => 'required|integer',
            'status' => 'required|in:active,expired,suspended',
        ]);

        $travelCard = TravelCard::create($validated);

        return response()->json($travelCard, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $travelCard = TravelCard::with(['passenger', 'route'])->findOrFail($id);

        return $travelCard;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'route_id' => 'sometimes|required|exists:routes,id',
            'card_type' => 'sometimes|required|in:single,return,weekly,monthly',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'sometimes|required|date',
            'total_trips' => 'sometimes|required|integer',
            'status' => 'sometimes|required|in:active,expired,suspended',
        ]);

        $travelCard = TravelCard::findOrFail($id);
        $travelCard->update($validated);

        return $travelCard;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $travelCard = TravelCard::findOrFail($id);
        $travelCard->delete();

        return response()->json(['message' => 'Travel card deleted successfully']);
    }
}
