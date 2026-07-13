<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reservations = Reservation::with(['passenger', 'trip', 'travelCard'])->paginate(15);

        return $reservations;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'passenger_id' => 'required|exists:passengers,id',
            'trip_id' => 'required|exists:trips,id',
            'travel_card_id' => 'required|exists:travel_cards,id',
            'seat_number' => 'required|integer',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'required|date',
            'status' => 'required|in:booked,cancelled,completed',
        ]);

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

        $reservation = Reservation::create($validated);

        return response()->json($reservation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $reservation = Reservation::with(['passenger', 'trip', 'travelCard'])->findOrFail($id);

        return $reservation;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'passenger_id' => 'sometimes|required|exists:passengers,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'seat_number' => 'sometimes|required|integer',
            'pickup_location' => 'nullable|string',
            'reservation_time' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:booked,cancelled,completed',
        ]);

        $reservation = Reservation::findOrFail($id);
        $reservation->update($validated);

        return $reservation;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully']);
    }
}
