<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\Route;
use App\Models\TravelCard;
use Illuminate\Http\Request;

class TravelCardController extends Controller
{
    /**
     * How many trips and how many days of validity a card_type grants.
     * Mirrors the multiplier basis TravelCard::calculatePrice() already uses.
     */
    private function computeCardTerms(string $cardType): array
    {
        return match ($cardType) {
            'single' => ['total_trips' => 1, 'expiry_days' => 1],
            'return' => ['total_trips' => 2, 'expiry_days' => 3],
            'weekly' => ['total_trips' => 5, 'expiry_days' => 7],
            'monthly' => ['total_trips' => 20, 'expiry_days' => 30],
        };
    }

    /**
     * Both ends of a card's segment have to be stops on the card's own route,
     * or there is no sequence to measure the distance along — and distance is
     * what the fare is banded on. Returns a 422 response, or null if valid.
     */
    private function segmentError($routeId, $fromStationId, $toStationId)
    {
        $route = Route::find($routeId);

        if (! $route || $route->distanceBetweenStations((int) $fromStationId, (int) $toStationId) === null) {
            return response()->json([
                'message' => 'Both stations must be stops on this route.',
            ], 422);
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = TravelCard::with(['passenger', 'route', 'fromStation', 'toStation'])
            ->withCount([
                'reservations as consumed_reservations_count' => fn ($q) => $q->whereIn('status', ['booked', 'completed']),
                'boardings as walkup_boardings_count' => fn ($q) => $q->whereNull('reservation_id'),
            ]);

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
            // The segment the card is bought for — what its price is banded on.
            'from_station_id' => 'required|exists:stations,id',
            'to_station_id' => 'required|exists:stations,id|different:from_station_id',
            'card_type' => 'required|in:single,return,weekly,monthly',
            'status' => 'required|in:active,expired,suspended',
        ]);

        if ($error = $this->segmentError($validated['route_id'], $validated['from_station_id'], $validated['to_station_id'])) {
            return $error;
        }

        // A passenger can only buy a travel card tied to their own profile.
        // Drivers may create one for any passenger — this covers a walk-up
        // rider paying cash directly to the driver for a single-trip card.
        if ($user->role === 'passenger') {
            $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
            $validated['passenger_id'] = $ownPassenger->id;
        }

        // total_trips/expiry_date are computed server-side, not client-set —
        // otherwise a client could send total_trips: 999999.
        $terms = $this->computeCardTerms($validated['card_type']);
        $validated['total_trips'] = $terms['total_trips'];
        $validated['purchase_date'] = now()->toDateString();
        $validated['expiry_date'] = now()->addDays($terms['expiry_days'])->toDateString();

        $travelCard = TravelCard::create($validated);

        return response()->json($travelCard, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $travelCard = TravelCard::with(['passenger', 'route', 'fromStation', 'toStation'])->findOrFail($id);

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
            'from_station_id' => 'sometimes|required|exists:stations,id',
            'to_station_id' => 'sometimes|required|exists:stations,id|different:from_station_id',
            'card_type' => 'sometimes|required|in:single,return,weekly,monthly',
            'status' => 'sometimes|required|in:active,expired,suspended',
        ]);

        // Re-check the segment against whatever the card would look like after
        // the patch, so moving either end (or the route) can't leave a card
        // whose price has nothing to measure.
        $effective = array_merge(
            $travelCard->only(['route_id', 'from_station_id', 'to_station_id']),
            $validated,
        );

        if ($effective['from_station_id'] && $effective['to_station_id']) {
            if ($error = $this->segmentError($effective['route_id'], $effective['from_station_id'], $effective['to_station_id'])) {
                return $error;
            }
        }

        if (isset($validated['card_type'])) {
            $terms = $this->computeCardTerms($validated['card_type']);
            $validated['total_trips'] = $terms['total_trips'];
            $validated['expiry_date'] = now()->addDays($terms['expiry_days'])->toDateString();
        }

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
