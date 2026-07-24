<?php

namespace App\Http\Controllers;

use App\Models\Driver;
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

        $query = Payment::with(['travelCard.passenger', 'collectedByDriver']);

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
     * Admin financial oversight: total billed (every payment, any status)
     * vs total received (payment_status = paid) for a period, plus the same
     * split per collecting driver — lets an admin catch a driver who
     * recorded a cash sale but never marked it paid (or the money never
     * actually showed up).
     */
    public function summary(Request $request)
    {
        $period = $request->query('period', 'day');

        // Day/week/month boundaries are computed in the operator's local zone
        // (config('app.display_timezone')) so "today" means the local calendar
        // day, then converted to UTC to match how created_at is stored. Without
        // this, the window was a UTC day shifted from the local one, so "today"
        // bled into the previous local evening.
        $tz = config('app.display_timezone');
        $localNow = now($tz);

        [$localStart, $localEnd] = match ($period) {
            'week' => [$localNow->copy()->startOfWeek(), $localNow->copy()->endOfWeek()],
            'month' => [$localNow->copy()->startOfMonth(), $localNow->copy()->endOfMonth()],
            default => [$localNow->copy()->startOfDay(), $localNow->copy()->endOfDay()],
        };

        $start = $localStart->copy()->utc();
        $end = $localEnd->copy()->utc();

        $inPeriod = Payment::whereBetween('created_at', [$start, $end]);

        $totalBilled = (clone $inPeriod)->sum('amount');
        $totalReceived = (clone $inPeriod)->where('payment_status', 'paid')->sum('amount');

        $driverRows = Payment::whereBetween('created_at', [$start, $end])
            ->whereNotNull('collected_by_driver_id')
            ->selectRaw('collected_by_driver_id, SUM(amount) as billed, SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as received')
            ->groupBy('collected_by_driver_id')
            ->get();

        $drivers = Driver::whereIn('id', $driverRows->pluck('collected_by_driver_id'))->get()->keyBy('id');

        $byDriver = $driverRows->map(function ($row) use ($drivers) {
            $driver = $drivers->get($row->collected_by_driver_id);
            return [
                'driver_id' => $row->collected_by_driver_id,
                'driver_name' => $driver ? trim($driver->first_name . ' ' . $driver->last_name) : 'Unknown',
                'billed' => (float) $row->billed,
                'received' => (float) $row->received,
            ];
        })->values();

        return response()->json([
            'period' => $period,
            'start' => $localStart->toDateTimeString(),
            'end' => $localEnd->toDateTimeString(),
            'total_billed' => (float) $totalBilled,
            'total_received' => (float) $totalReceived,
            'by_driver' => $byDriver,
        ]);
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
            'collected_by_driver_id' => 'sometimes|nullable|exists:drivers,id',
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

        // A driver's own payment is always attributed to themselves,
        // regardless of what (if anything) was sent for this field.
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            $validated['collected_by_driver_id'] = $ownDriver?->id;
        }

        $travelCard = TravelCard::with('route')->findOrFail($validated['travel_card_id']);
    $validated['amount'] = $travelCard->calculatePrice();

        // Stamp when it was paid if it's coming in as paid and the caller
        // didn't supply a time — so "when was this paid" is answerable later.
        if (($validated['payment_status'] ?? null) === 'paid' && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $payment = Payment::with(['travelCard.passenger', 'collectedByDriver'])->findOrFail($id);

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
            'collected_by_driver_id' => 'sometimes|nullable|exists:drivers,id',
        ]);

        $travelCardID = $validated['travel_card_id'] ?? $payment->travel_card_id;
        $travelCard = TravelCard::with('route')->findOrFail($travelCardID);
        $validated['amount'] = $travelCard->calculatePrice();

        // Stamp paid_at when this update marks it paid and there's no time yet.
        if (($validated['payment_status'] ?? null) === 'paid' && empty($validated['paid_at']) && empty($payment->paid_at)) {
            $validated['paid_at'] = now();
        }

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
