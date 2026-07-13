<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = Payment::with('travelCard')->paginate(15);

        return $payments;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'travel_card_id' => 'required|exists:travel_cards,id',
            'amount' => 'required|numeric',
            'payment_method' => 'required|in:cash,credit_card,bank_transfer,wish',
            'payment_status' => 'required|in:unpaid,paid,failed',
            'paid_at' => 'nullable|date',
        ]);

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payment = Payment::with('travelCard')->findOrFail($id);

        return $payment;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'amount' => 'sometimes|required|numeric',
            'payment_method' => 'sometimes|required|in:cash,credit_card,bank_transfer,wish',
            'payment_status' => 'sometimes|required|in:unpaid,paid,failed',
            'paid_at' => 'nullable|date',
        ]);

        $payment = Payment::findOrFail($id);
        $payment->update($validated);

        return $payment;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
