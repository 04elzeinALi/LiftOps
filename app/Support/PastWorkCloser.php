<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;

/**
 * Closes out work whose time has simply passed.
 *
 * A driver who forgets to press "End Trip", or an admin who never revisits
 * yesterday's shift, used to leave the record sitting on 'scheduled' or
 * 'ongoing' forever. Those stale rows aren't harmless: passengers browse
 * trips by status, and every report that counts completed work would keep
 * missing days that plainly happened.
 *
 * TIMEZONE
 * trip_date/shift_date are local calendar dates and the times are local
 * wall-clock, but the app runs on UTC (config app.timezone) while operating
 * in app.display_timezone. Comparing those columns against a UTC now() would
 * be wrong by the offset — three hours in Beirut — closing this evening's
 * work early or leaving last night's open. So the comparison is made entirely
 * in local terms: local column values against a local "now".
 *
 * Only 'scheduled' and 'ongoing' are touched. 'cancelled' and 'emergency' are
 * decisions someone made, and this must never overwrite them.
 */
class PastWorkCloser
{
    /**
     * How long to wait before sweeping again. This runs off ordinary list
     * requests (there's no cron guaranteed here), so without a throttle every
     * page load would issue two writes.
     */
    private const THROTTLE_SECONDS = 60;

    private const OPEN_STATUSES = ['scheduled', 'ongoing'];

    /**
     * Runs at most once per THROTTLE_SECONDS across the whole app.
     *
     * Cache::add is atomic — the first caller to claim the key wins and does
     * the work, everyone else returns immediately — so two simultaneous
     * requests can't both sweep.
     */
    public static function sweepThrottled(): void
    {
        if (! Cache::add('past-work-closer', true, self::THROTTLE_SECONDS)) {
            return;
        }

        self::sweep();
    }

    /**
     * @return array{trips:int, shifts:int} how many rows were closed
     */
    public static function sweep(): array
    {
        $localNow = now(config('app.display_timezone'))->toDateTimeString();

        // A trip is over once its arrival time has passed. Trips with no
        // arrival time recorded fall back to the end of their own day, so a
        // missing timetable can't close a trip early.
        $trips = Trip::whereIn('status', self::OPEN_STATUSES)
            ->whereRaw(
                "CONCAT(trip_date, ' ', COALESCE(arrival_time, '23:59:59')) <= ?",
                [$localNow]
            )
            ->update(['status' => 'completed']);

        // Same for shifts, except a shift can run past midnight: when the end
        // time is at or before the start time the shift finishes on the
        // FOLLOWING day, and treating it as same-day would close a night
        // shift hours before it actually ends.
        $shifts = Shift::whereIn('status', self::OPEN_STATUSES)
            ->whereRaw(
                "CONCAT(
                    CASE WHEN end_time <= start_time
                         THEN DATE_ADD(shift_date, INTERVAL 1 DAY)
                         ELSE shift_date END,
                    ' ', end_time
                 ) <= ?",
                [$localNow]
            )
            ->update(['status' => 'completed']);

        return ['trips' => $trips, 'shifts' => $shifts];
    }
}
