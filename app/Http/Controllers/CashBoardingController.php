<?php

namespace App\Http\Controllers;

use App\Models\CashBoarding;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * A cash customer: someone who boards without a reservation, an account, or
 * a travel card, pays the driver directly, and rides once. Deliberately
 * minimal — a name, an optional note, which trip (and so which route), and
 * the amount collected. Not folded into the Payments report, since Payment
 * rows are tied to a travel_card_id these riders don't have; this amount
 * lives only on the record itself. Not counted against a trip's seat
 * capacity either — see BoardingController's $occupied math, which this
 * intentionally doesn't touch.
 */
class CashBoardingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = CashBoarding::with(['trip', 'driver', 'fromStation', 'toStation']);

        // A driver only sees the cash customers they personally recorded.
        if ($user->role === 'driver') {
            $query->whereHas('driver', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->query('trip_id'));
        }

        return $query->orderByDesc('boarded_at')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'customer_name' => 'required|string|max:255',
            'from_station_id' => 'nullable|exists:stations,id',
            'to_station_id' => 'nullable|exists:stations,id|different:from_station_id',
            'amount' => 'required|numeric|min:0',
            'boarded_at' => 'required|date',
        ]);

        $trip = Trip::findOrFail($validated['trip_id']);

        // A driver may only record a cash customer on a trip they are
        // actually assigned to drive — mirrors BoardingController::store().
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();

            if (! $ownDriver || $trip->driver_id !== $ownDriver->id) {
                return response()->json([
                    'message' => 'Forbidden: you are not the driver for this trip.',
                ], 403);
            }

            $validated['driver_id'] = $ownDriver->id;
        } else {
            // An admin recording on a driver's behalf doesn't pick a driver
            // separately — it's whoever is actually driving that trip.
            $validated['driver_id'] = $trip->driver_id;
        }

        $cashBoarding = CashBoarding::create($validated);

        return response()->json($cashBoarding->load(['trip', 'driver', 'fromStation', 'toStation']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $cashBoarding = CashBoarding::with(['trip', 'driver', 'fromStation', 'toStation'])->findOrFail($id);

        $this->authorizeView($request, $cashBoarding);

        return $cashBoarding;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $cashBoarding = CashBoarding::findOrFail($id);

        $this->authorizeView($request, $cashBoarding);

        $cashBoarding->delete();

        return response()->json(['message' => 'Cash boarding deleted successfully']);
    }

    /**
     * Admins may view/delete any record; a driver only their own.
     */
    private function authorizeView(Request $request, CashBoarding $cashBoarding): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            if ($ownDriver && $cashBoarding->driver_id === $ownDriver->id) {
                return;
            }
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
