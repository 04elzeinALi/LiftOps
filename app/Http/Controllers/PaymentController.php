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

        $query = Payment::with([
            'travelCard.passenger',
            'travelCard.fromStation',
            'travelCard.toStation',
            'collectedByDriver',
            // The card's latest live booking, with its trip's driver — used to
            // fill "Collected By" with the trip driver's name (see below).
            'travelCard.reservations' => fn ($q) => $q
                ->where('status', 'booked')
                ->orderByDesc('reservation_time')
                ->with('trip.driver'),
        ]);

        // A passenger only sees payments on their own cards. Drivers see
        // everything (no trip_id on this table to scope by) so they can
        // confirm a cash payment they just recorded went through.
        if ($user->role === 'passenger') {
            $query->whereHas(
                'travelCard.passenger',
                fn ($q) => $q->where('user_id', $user->id)
            );
        }

        // Optional period filter (day/week/month/year/all) so the admin can
        // view just the payments made in that window instead of all of them.
        // Same local-timezone window as the summary endpoint. "all" resolves
        // to no bounds, so it's simply not applied.
        if ($request->filled('period')) {
            $window = $this->periodWindow($request->query('period'));
            if ($window) {
                $query->whereBetween('created_at', $window);
            }
        }

        $payments = $query->latest()->paginate(15);

        // Attach the driver of the card's most recent booked trip so the UI
        // can show it in the "Collected By" column. A card can span several
        // trips/drivers, so "most recent booked" is the meaningful one.
        $payments->getCollection()->transform(function ($payment) {
            $driver = $payment->travelCard?->reservations?->first()?->trip?->driver;
            $payment->trip_driver_name = $driver
                ? trim($driver->first_name . ' ' . $driver->last_name)
                : null;

            return $payment;
        });

        return $payments;
    }

    /**
     * Resolve a period keyword (day/week/month/year/all) to a [startUtc,
     * endUtc] window computed in the operator's local timezone. Shared by
     * index() and summary() so "today" means the same local calendar day in
     * both. "all" has no window at all — null tells the caller to skip the
     * date filter entirely rather than pass some arbitrarily wide range.
     */
    private function periodWindow(string $period): ?array
    {
        if ($period === 'all') {
            return null;
        }

        $tz = config('app.display_timezone');
        $localNow = now($tz);

        [$localStart, $localEnd] = match ($period) {
            'week' => [$localNow->copy()->startOfWeek(), $localNow->copy()->endOfWeek()],
            'month' => [$localNow->copy()->startOfMonth(), $localNow->copy()->endOfMonth()],
            'year' => [$localNow->copy()->startOfYear(), $localNow->copy()->endOfYear()],
            default => [$localNow->copy()->startOfDay(), $localNow->copy()->endOfDay()],
        };

        return [$localStart->copy()->utc(), $localEnd->copy()->utc()];
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

        // Day/week/month/year boundaries are computed in the operator's local
        // zone (see periodWindow) then converted to UTC to match how
        // created_at is stored. Without this, the window was a UTC day
        // shifted from the local one, so "today" bled into the previous local
        // evening. "all" has no window — the queries below simply go unfiltered.
        $window = $this->periodWindow($period);

        $inPeriod = Payment::query();
        $driverQuery = Payment::whereNotNull('collected_by_driver_id');

        if ($window) {
            $inPeriod->whereBetween('created_at', $window);
            $driverQuery->whereBetween('created_at', $window);
        }

        $totalBilled = (clone $inPeriod)->sum('amount');
        $totalReceived = (clone $inPeriod)->where('payment_status', 'paid')->sum('amount');

        $driverRows = $driverQuery
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
            'start' => $window ? $window[0]->toDateTimeString() : null,
            'end' => $window ? $window[1]->toDateTimeString() : null,
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
            // Optional: passengers never set it (derived from method below),
            // and admin/driver default to 'unpaid' if they omit it.
            'payment_status' => 'nullable|in:unpaid,paid,failed',
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

            // Confirmation policy: a passenger can't freely mark a payment
            // 'paid'. Online methods (card/bank/wish) act as an instant mock
            // gateway and auto-confirm; cash starts 'unpaid' until a
            // driver/admin confirms receipt. Whatever status the passenger
            // sent is ignored here. Admin/driver keep the status they set.
            $online = in_array($validated['payment_method'], ['credit_card', 'bank_transfer', 'wish']);
            $validated['payment_status'] = $online ? 'paid' : 'unpaid';
        }

        // A driver's own payment is always attributed to themselves,
        // regardless of what (if anything) was sent for this field.
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->first();
            $validated['collected_by_driver_id'] = $ownDriver?->id;
        }

        // Admin/driver default: an omitted status means unpaid.
        $validated['payment_status'] = $validated['payment_status'] ?? 'unpaid';

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
        $user = $request->user();
        $payment = Payment::with('travelCard.passenger')->findOrFail($id);

        // Admin: full edit. Owning passenger: may edit their own payment but
        // never mark it paid. Driver: may only confirm/adjust status (e.g.
        // mark a cash payment received). Anyone else: forbidden.
        $isAdmin = $user->role === 'admin';
        $isOwningPassenger = $user->role === 'passenger'
            && $payment->travelCard?->passenger?->user_id === $user->id;
        $isDriver = $user->role === 'driver';

        if (! $isAdmin && ! $isOwningPassenger && ! $isDriver) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'travel_card_id' => 'sometimes|required|exists:travel_cards,id',
            'payment_method' => 'sometimes|required|in:cash,credit_card,bank_transfer,wish',
            'payment_status' => 'sometimes|required|in:unpaid,paid,failed',
            'paid_at' => 'nullable|date',
            'collected_by_driver_id' => 'sometimes|nullable|exists:drivers,id',
        ]);

        // A passenger can never confirm a payment as paid (see store policy).
        if ($isOwningPassenger) {
            unset($validated['payment_status']);
        }

        // A driver may only touch status/confirmation fields — not reassign
        // the card or rewrite the amount.
        if ($isDriver) {
            $validated = array_intersect_key(
                $validated,
                array_flip(['payment_status', 'paid_at', 'collected_by_driver_id'])
            );
        }

        // Only recompute the amount when the card actually changes. A plain
        // status toggle must NOT rewrite a historical amount at today's fare.
        if (isset($validated['travel_card_id'])) {
            $travelCard = TravelCard::with('route')->findOrFail($validated['travel_card_id']);
            $validated['amount'] = $travelCard->calculatePrice();
        }

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
