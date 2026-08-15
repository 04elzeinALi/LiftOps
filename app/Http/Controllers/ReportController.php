<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\CashBoarding;
use App\Models\Driver;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Route;
use App\Support\ReportWindow;
use Illuminate\Http\Request;

/**
 * Operational reports. Admin-only (gated in routes/api.php).
 *
 * Every total is aggregated in SQL rather than by loading rows and summing
 * them in PHP or the browser — a paginated fetch would quietly drop
 * everything past the first page and report a confidently wrong number.
 *
 * Date filtering goes through ReportWindow, which carries both a UTC and a
 * local-date reading of the same range; see that class for why mixing them
 * up shifts figures across midnight.
 */
class ReportController extends Controller
{
    /**
     * Money taken, and what's still owed.
     *
     * Revenue lives in two places and both are counted here:
     *
     *   - `payments`, for anything bought against a travel card
     *   - `cash_boardings`, for a one-off cash rider who never had a card
     *
     * Cash boardings create no Payment row (Payment keys off travel_card_id,
     * which those riders don't have), so a revenue figure taken from
     * `payments` alone silently under-reports every cash customer. They are
     * treated as received on the spot: the driver physically took the money.
     */
    public function revenue(Request $request)
    {
        $window = ReportWindow::fromRequest($request);

        $payments = $window->applyToTimestamp(Payment::query(), 'payments.created_at');
        $billed = (float) (clone $payments)->sum('amount');
        $received = (float) (clone $payments)->where('payment_status', 'paid')->sum('amount');
        $failed = (float) (clone $payments)->where('payment_status', 'failed')->sum('amount');

        $cashBoardings = $window->applyToTimestamp(CashBoarding::query(), 'cash_boardings.boarded_at');
        $cashTaken = (float) (clone $cashBoardings)->sum('amount');
        $cashCount = (clone $cashBoardings)->count();

        // What's been billed but not collected — almost always cash a driver
        // hasn't handed in yet.
        $outstanding = (float) (clone $payments)->where('payment_status', 'unpaid')->sum('amount');

        $byMethod = (clone $payments)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as billed, SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as received')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->payment_method,
                'count' => (int) $r->count,
                'billed' => (float) $r->billed,
                'received' => (float) $r->received,
            ]);

        // Cash customers are their own line so the figure reconciles against
        // the card-based methods above rather than hiding inside one of them.
        if ($cashCount > 0) {
            $byMethod->push([
                'method' => 'cash_customer',
                'count' => $cashCount,
                'billed' => $cashTaken,
                'received' => $cashTaken,
            ]);
        }

        $byRoute = (clone $payments)
            ->join('travel_cards', 'payments.travel_card_id', '=', 'travel_cards.id')
            ->join('routes', 'travel_cards.route_id', '=', 'routes.id')
            ->selectRaw('routes.id as route_id, routes.route_name, COUNT(*) as count, SUM(payments.amount) as billed, SUM(CASE WHEN payments.payment_status = "paid" THEN payments.amount ELSE 0 END) as received')
            ->groupBy('routes.id', 'routes.route_name')
            ->orderByDesc('billed')
            ->get()
            ->map(fn ($r) => [
                'route_id' => (int) $r->route_id,
                'route_name' => $r->route_name,
                'count' => (int) $r->count,
                'billed' => (float) $r->billed,
                'received' => (float) $r->received,
            ]);

        $byCardType = (clone $payments)
            ->join('travel_cards', 'payments.travel_card_id', '=', 'travel_cards.id')
            ->selectRaw('travel_cards.card_type, COUNT(*) as count, SUM(payments.amount) as billed')
            ->groupBy('travel_cards.card_type')
            ->orderByDesc('billed')
            ->get()
            ->map(fn ($r) => [
                'card_type' => $r->card_type,
                'count' => (int) $r->count,
                'billed' => (float) $r->billed,
            ]);

        return response()->json([
            'window' => $window->toArray(),
            'totals' => [
                'billed' => round($billed + $cashTaken, 2),
                'received' => round($received + $cashTaken, 2),
                'outstanding' => round($outstanding, 2),
                'failed' => round($failed, 2),
                'card_revenue' => round($billed, 2),
                'cash_customer_revenue' => round($cashTaken, 2),
            ],
            'by_method' => $byMethod->values(),
            'by_route' => $byRoute,
            'by_card_type' => $byCardType,
        ]);
    }

    /**
     * The individual transactions behind one slice of revenue() — a payment
     * method, a route, or a card type — so an admin can see exactly which
     * dates, drivers and passengers made up a total instead of just the sum.
     *
     * Cash customers only exist as a `method` slice ('cash_customer'): they
     * have no travel card, so they can't be sliced by route or card type —
     * see the revenue() docblock for why they're a separate table entirely.
     */
    public function revenueDetail(Request $request)
    {
        $dimension = $request->query('dimension');
        $value = $request->query('value');

        if (! in_array($dimension, ['method', 'route', 'card_type'], true) || $value === null || $value === '') {
            abort(422, 'dimension must be one of method, route, card_type, and value is required.');
        }

        $window = ReportWindow::fromRequest($request);

        if ($dimension === 'method' && $value === 'cash_customer') {
            $boardings = $window->applyToTimestamp(CashBoarding::query(), 'cash_boardings.boarded_at')
                ->with(['driver', 'trip.shift.route', 'trip.schedule.route'])
                ->orderByDesc('boarded_at')
                ->get();

            $rows = $boardings->map(fn ($b) => [
                'id' => 'cash-' . $b->id,
                'date' => optional($b->boarded_at)->toIso8601String(),
                'driver_name' => $b->driver ? trim($b->driver->first_name . ' ' . $b->driver->last_name) : 'Unknown driver',
                'passenger_name' => $b->customer_name ?: 'Cash customer',
                'route_name' => $b->trip?->route?->route_name,
                'card_type' => null,
                'payment_method' => 'cash_customer',
                'payment_status' => 'paid',
                'amount' => (float) $b->amount,
            ]);

            $label = 'Cash customers';
        } else {
            $query = $window->applyToTimestamp(Payment::query(), 'payments.created_at')
                ->with(['travelCard.passenger', 'travelCard.route', 'collectedByDriver']);

            match ($dimension) {
                'method' => $query->where('payment_method', $value),
                'route' => $query->whereHas('travelCard', fn ($q) => $q->where('route_id', $value)),
                'card_type' => $query->whereHas('travelCard', fn ($q) => $q->where('card_type', $value)),
            };

            $payments = $query->orderByDesc('created_at')->get();

            $rows = $payments->map(fn ($p) => [
                'id' => 'payment-' . $p->id,
                'date' => optional($p->created_at)->toIso8601String(),
                'driver_name' => $p->collectedByDriver
                    ? trim($p->collectedByDriver->first_name . ' ' . $p->collectedByDriver->last_name)
                    : null,
                'passenger_name' => $p->travelCard?->passenger
                    ? trim($p->travelCard->passenger->first_name . ' ' . $p->travelCard->passenger->last_name)
                    : 'Unknown passenger',
                'route_name' => $p->travelCard?->route?->route_name,
                'card_type' => $p->travelCard?->card_type,
                'payment_method' => $p->payment_method,
                'payment_status' => $p->payment_status,
                'amount' => (float) $p->amount,
            ]);

            $label = match ($dimension) {
                'method' => ucwords(str_replace('_', ' ', $value)),
                // Looked up directly rather than off the first row, so the
                // label still reads correctly when there are no payments in
                // the selected window (an empty table, not a blank title).
                'route' => Route::find($value)?->route_name ?? 'Route',
                'card_type' => ucwords($value),
            };
        }

        return response()->json([
            'window' => $window->toArray(),
            'dimension' => $dimension,
            'value' => $value,
            'label' => $label,
            'rows' => $rows->values(),
            'totals' => [
                'count' => $rows->count(),
                'billed' => round($rows->sum('amount'), 2),
                'received' => round($rows->where('payment_status', 'paid')->sum('amount'), 2),
            ],
        ]);
    }

    /**
     * What each driver took in cash, and how much of it is confirmed.
     *
     * Same two sources as revenue(), for the same reason: a driver who sells
     * a travel card leaves a Payment with collected_by_driver_id, but a
     * driver who takes a cash customer leaves only a CashBoarding. Counting
     * one without the other tells a driver they owe the wrong amount.
     */
    public function driverCash(Request $request)
    {
        $window = ReportWindow::fromRequest($request);

        $cardRows = $window->applyToTimestamp(Payment::whereNotNull('collected_by_driver_id'), 'payments.created_at')
            ->selectRaw('collected_by_driver_id as driver_id, COUNT(*) as count, SUM(amount) as billed, SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as confirmed')
            ->groupBy('collected_by_driver_id')
            ->get()
            ->keyBy('driver_id');

        $cashRows = $window->applyToTimestamp(CashBoarding::query(), 'cash_boardings.boarded_at')
            ->selectRaw('driver_id, COUNT(*) as count, SUM(amount) as taken')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $driverIds = $cardRows->keys()->merge($cashRows->keys())->unique()->values();
        $drivers = Driver::whereIn('id', $driverIds)->get()->keyBy('id');

        $rows = $driverIds->map(function ($id) use ($cardRows, $cashRows, $drivers) {
            $card = $cardRows->get($id);
            $cash = $cashRows->get($id);
            $driver = $drivers->get($id);

            $cardBilled = (float) ($card->billed ?? 0);
            $cardConfirmed = (float) ($card->confirmed ?? 0);
            $cashTaken = (float) ($cash->taken ?? 0);

            return [
                'driver_id' => (int) $id,
                'driver_name' => $driver ? trim($driver->first_name . ' ' . $driver->last_name) : 'Unknown driver',
                'card_sales_count' => (int) ($card->count ?? 0),
                'cash_customer_count' => (int) ($cash->count ?? 0),
                // Cash customers are money already in the driver's hand, so
                // they count as confirmed; card sales only count once the
                // payment has actually been marked paid.
                'collected' => round($cardBilled + $cashTaken, 2),
                'confirmed' => round($cardConfirmed + $cashTaken, 2),
                'outstanding' => round($cardBilled - $cardConfirmed, 2),
            ];
        })->sortByDesc('collected')->values();

        return response()->json([
            'window' => $window->toArray(),
            'totals' => [
                'collected' => round($rows->sum('collected'), 2),
                'confirmed' => round($rows->sum('confirmed'), 2),
                'outstanding' => round($rows->sum('outstanding'), 2),
            ],
            'by_driver' => $rows,
        ]);
    }

    /**
     * What the fleet cost to keep running.
     *
     * Filtered on scheduled_at (a local date) — the day the work was booked
     * for, which is how an operator thinks about a maintenance schedule.
     */
    public function fleet(Request $request)
    {
        $window = ReportWindow::fromRequest($request);

        $records = $window->applyToDate(Maintenance::query(), 'maintenance.scheduled_at');

        $totalCost = (float) (clone $records)->sum('cost');
        $count = (clone $records)->count();
        $openCount = (clone $records)->whereIn('maintenance_status', ['scheduled', 'in_progress'])->count();

        $byBus = (clone $records)
            ->join('buses', 'maintenance.bus_id', '=', 'buses.id')
            ->selectRaw('buses.id as bus_id, buses.plate_number, COUNT(*) as jobs, SUM(maintenance.cost) as cost')
            ->groupBy('buses.id', 'buses.plate_number')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'bus_id' => (int) $r->bus_id,
                'plate_number' => $r->plate_number,
                'jobs' => (int) $r->jobs,
                'cost' => (float) ($r->cost ?? 0),
            ]);

        $byType = (clone $records)
            ->selectRaw('maintenance_type, COUNT(*) as jobs, SUM(cost) as cost')
            ->groupBy('maintenance_type')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'maintenance_type' => $r->maintenance_type,
                'jobs' => (int) $r->jobs,
                'cost' => (float) ($r->cost ?? 0),
            ]);

        return response()->json([
            'window' => $window->toArray(),
            'totals' => [
                'cost' => round($totalCost, 2),
                'jobs' => $count,
                'open_jobs' => $openCount,
                'buses_off_road' => Bus::where('status', 'maintenance')->count(),
            ],
            'by_bus' => $byBus,
            'by_type' => $byType,
        ]);
    }
}
