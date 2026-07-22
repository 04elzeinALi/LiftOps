<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TravelCard;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::with('travelCard');

        // A passenger only sees payments on their own cards. Drivers see
        // everything (no trip_id on this table to scope by) so they can
        // confirm a cash payment they just recorded went through.
        if ($user->role === 'passenger') {
            $query->whereHas(
                'travelCard.passenger',
                fn ($q) => $q->where('user_id', $user->id)
            );
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
            'travel_card_id' => 'required|exists:travel_cards,id',
            'payment_method' => 'required|in:cash,credit_card,bank_transfer,wish',
            'payment_status' => 'required|in:unpaid,paid,failed',
            'paid_at' => 'nullable|date',
        ]);

        // A passenger can only pay towards their own travel card. Drivers
        // may record a payment for any card — this covers a walk-up rider
        // handing the driver cash directly.
        if ($user->role === 'passenger') {
            $ownsCard = TravelCard::where('id', $validated['travel_card_id'])
                ->whereHas('passenger', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

            if (! $ownsCard) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }
        $travelCard = TravelCard::with('route')->findOrFail($validated['travel_card_id']);
    $validated['amount'] = $travelCard->calculatePrice();

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $payment = Payment::with('travelCard')->findOrFail($id);

        $this->authorizeView($request, $payment);

        return $payment;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorizeWrite($request, $payment);

        $validated = $request->validate([
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'payment_method' => 'sometimes|required|in:cash,credit_card,bank_transfer,wish',
            'payment_status' => 'sometimes|required|in:unpaid,paid,failed',
            'paid_at' => 'nullable|date',
        ]);

        $travelCardID = $validated['travel_card_id'] ?? $payment->travel_card_id;
        $travelCard = TravelCard::with('route')->findOrFail($travelCardID);
        $validated['amount'] = $travelCard->calculatePrice();
        $payment->update($validated);

        return $payment;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorizeWrite($request, $payment);

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    /**
     * Admins and drivers may view any payment; a passenger may only view
     * payments tied to their own travel cards.
     */
    private function authorizeView(Request $request, Payment $payment): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'driver') {
            return;
        }

        if ($user->role === 'passenger' && $payment->travelCard?->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }

    /**
     * Only admins, or the owning passenger, may update/delete a payment.
     * Drivers can record payments (cash sales) but not edit existing ones.
     */
    private function authorizeWrite(Request $request, Payment $payment): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $payment->travelCard?->passenger?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
