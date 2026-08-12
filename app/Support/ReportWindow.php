<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The date range a report covers.
 *
 * Every report has to answer "which day did this belong to", and the app
 * stores that in two incompatible ways: timestamps (payments.paid_at,
 * boarding.boarded_at) are UTC, while date columns (trips.trip_date,
 * shifts.shift_date, maintenance.scheduled_at) are already local calendar
 * dates. Comparing a UTC timestamp against a local day boundary is what makes
 * revenue leak across midnight — the same class of bug the driver's day
 * stepper had.
 *
 * So this carries BOTH readings of the same window, and each query picks the
 * one matching the column it filters:
 *
 *   - utcStart/utcEnd  for datetime columns stored in UTC
 *   - localFrom/localTo for plain date columns already in local terms
 */
class ReportWindow
{
    public function __construct(
        public readonly ?Carbon $utcStart,
        public readonly ?Carbon $utcEnd,
        public readonly ?string $localFrom,
        public readonly ?string $localTo,
        public readonly string $label,
    ) {
    }

    /**
     * Reads `period` (day|week|month|year|all) or an explicit `from`/`to`
     * pair off the request. An explicit range wins — a report someone typed
     * dates into shouldn't be quietly overridden by a preset.
     */
    public static function fromRequest(Request $request): self
    {
        $tz = config('app.display_timezone');

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            $localStart = Carbon::parse($from, $tz)->startOfDay();
            $localEnd = Carbon::parse($to, $tz)->endOfDay();

            return new self(
                $localStart->copy()->utc(),
                $localEnd->copy()->utc(),
                $localStart->toDateString(),
                $localEnd->toDateString(),
                $localStart->format('d/m/Y') . ' – ' . $localEnd->format('d/m/Y'),
            );
        }

        $period = $request->query('period', 'month');

        if ($period === 'all') {
            return new self(null, null, null, null, 'All time');
        }

        $localNow = now($tz);

        [$localStart, $localEnd, $label] = match ($period) {
            'day' => [$localNow->copy()->startOfDay(), $localNow->copy()->endOfDay(), 'Today'],
            'week' => [$localNow->copy()->startOfWeek(), $localNow->copy()->endOfWeek(), 'This week'],
            'year' => [$localNow->copy()->startOfYear(), $localNow->copy()->endOfYear(), 'This year'],
            default => [$localNow->copy()->startOfMonth(), $localNow->copy()->endOfMonth(), 'This month'],
        };

        return new self(
            $localStart->copy()->utc(),
            $localEnd->copy()->utc(),
            $localStart->toDateString(),
            $localEnd->toDateString(),
            $label,
        );
    }

    public function isUnbounded(): bool
    {
        return $this->utcStart === null;
    }

    /**
     * Constrains a query on a UTC datetime column (paid_at, boarded_at,
     * created_at).
     */
    public function applyToTimestamp($query, string $column)
    {
        if ($this->isUnbounded()) {
            return $query;
        }

        return $query->whereBetween($column, [$this->utcStart, $this->utcEnd]);
    }

    /**
     * Constrains a query on a plain local date column (trip_date,
     * shift_date, scheduled_at).
     */
    public function applyToDate($query, string $column)
    {
        if ($this->isUnbounded()) {
            return $query;
        }

        return $query->whereBetween($column, [$this->localFrom, $this->localTo]);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'from' => $this->localFrom,
            'to' => $this->localTo,
        ];
    }
}
