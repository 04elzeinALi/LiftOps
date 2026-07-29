<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['driver_id', 'bus_id', 'route_id', 'shift_date', 'start_time', 'end_time', 'rounds', 'status'])]
class Shift extends Model
{
    use HasFactory;

    protected $casts = ['shift_date' => 'date'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * How many legs this shift runs: a round is one out-and-back, so each
     * round is two legs.
     */
    public function legCount(): int
    {
        return max(1, (int) $this->rounds) * 2;
    }

    /**
     * The legs this shift implies, as plain arrays ready to become trips.
     *
     * Exact timings aren't realistic on this corridor, so rather than a
     * per-leg timetable the shift's window is simply divided evenly: a
     * 08:00-17:00 shift of 2 rounds gives four 2h15 legs, alternating
     * outbound and inbound, the driver ending back where they started.
     */
    public function legPlan(): array
    {
        $start = Carbon::parse($this->shift_date->toDateString() . ' ' . $this->start_time);
        $end = Carbon::parse($this->shift_date->toDateString() . ' ' . $this->end_time);

        // An end time before the start means the shift runs past midnight.
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $legs = $this->legCount();
        $minutesPerLeg = $start->diffInMinutes($end) / $legs;

        return collect(range(0, $legs - 1))->map(function ($i) use ($start, $minutesPerLeg) {
            $legStart = $start->copy()->addMinutes((int) round($i * $minutesPerLeg));
            $legEnd = $start->copy()->addMinutes((int) round(($i + 1) * $minutesPerLeg));

            return [
                'round_number' => intdiv($i, 2) + 1,
                'direction' => $i % 2 === 0 ? 'outbound' : 'inbound',
                'departure_time' => $legStart->format('H:i:s'),
                'arrival_time' => $legEnd->format('H:i:s'),
            ];
        })->all();
    }

    /**
     * Bring this shift's trips in line with its leg plan.
     *
     * Legs are matched on (round_number, direction) so re-syncing after the
     * admin shifts the hours updates the existing trips in place — their
     * reservations and boardings stay attached — rather than deleting and
     * recreating them. Legs that no longer exist (the round count was reduced)
     * are only removed when nothing is booked on them.
     */
    public function syncTrips(): void
    {
        $plan = collect($this->legPlan());
        $existing = $this->trips()->get()->keyBy(fn ($t) => $t->round_number . ':' . $t->direction);

        foreach ($plan as $leg) {
            $key = $leg['round_number'] . ':' . $leg['direction'];
            $trip = $existing->get($key);

            $attributes = $leg + [
                'shift_id' => $this->id,
                'driver_id' => $this->driver_id,
                'bus_id' => $this->bus_id,
                'trip_date' => $this->shift_date->toDateString(),
            ];

            if ($trip) {
                // Don't drag a finished or cancelled leg back to scheduled.
                $trip->update($attributes);
            } else {
                Trip::create($attributes + ['status' => 'scheduled']);
            }
        }

        $keep = $plan->map(fn ($leg) => $leg['round_number'] . ':' . $leg['direction'])->all();

        foreach ($existing as $key => $trip) {
            if (in_array($key, $keep, true)) {
                continue;
            }

            if ($trip->reservations()->exists() || $trip->boardings()->exists()) {
                continue;
            }

            $trip->delete();
        }
    }
}
