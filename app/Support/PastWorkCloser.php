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
        // Only work from a PREVIOUS local day is closed — never today's.
        //
        // Comparing against the current time instead looked reasonable and was
        // unusable: an admin setting up a 08:00-17:00 shift during the evening
        // watched it flip to 'completed' on the next refresh, because by the
        // clock its window had indeed passed. Same for a trip added an hour
        // after it was due out. Today's work is what someone is actively
        // arranging, and this has no business touching it.
        //
        // A day boundary is also the only rule that doesn't need to guess how
        // late is "late enough": once the date has rolled over, the work is
        // unambiguously behind us.
        $localToday = now(config('app.display_timezone'))->toDateString();

        $trips = Trip::whereIn('status', self::OPEN_STATUSES)
            ->whereDate('trip_date', '<', $localToday)
            ->update(['status' => 'completed']);

        // A shift can run past midnight: when the end time is at or before the
        // start time it finishes on the FOLLOWING day, so that's the day that
        // has to be behind us — otherwise last night's 22:00-02:00 shift would
        // be closed while it was still running.
        $shifts = Shift::whereIn('status', self::OPEN_STATUSES)
            ->whereRaw(
                "(CASE WHEN end_time <= start_time
                       THEN DATE_ADD(shift_date, INTERVAL 1 DAY)
                       ELSE shift_date END) < ?",
                [$localToday]
            )
            ->update(['status' => 'completed']);

        return ['trips' => $trips, 'shifts' => $shifts];
    }
}
