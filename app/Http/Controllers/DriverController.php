<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $drivers = Driver::with('user')->paginate(15);

        return $drivers;
    }

    /**
     * Everything this driver did on one local calendar day (admin view):
     * trips driven, cash they collected (to reconcile against what they
     * turned in), and attendance. Trips filter by trip_date (a calendar
     * date); timestamped records use the local-day-to-UTC window.
     */
    public function activity(Request $request, string $id)
    {
        $driver = Driver::with('user')->findOrFail($id);

        $request->validate(['date' => 'nullable|date']);

        $tz = config('app.display_timezone');
        $date = $request->query('date', now($tz)->toDateString());
        $start = Carbon::parse($date, $tz)->startOfDay()->utc();
        $end = Carbon::parse($date, $tz)->endOfDay()->utc();

        $trips = Trip::with(['schedule.route', 'bus'])
            ->where('driver_id', $driver->id)
            ->whereDate('trip_date', $date)
            ->get();

        $payments = Payment::with('travelCard.passenger')
            ->where('collected_by_driver_id', $driver->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->get();

        $attendance = Attendance::with('trip.schedule.route')
            ->where('driver_id', $driver->id)
            ->whereBetween('check_in', [$start, $end])
            ->latest('check_in')
            ->get();

        return response()->json([
            'driver' => $driver,
            'date' => $date,
            'trips' => $trips,
            'payments' => $payments,
            'attendance' => $attendance,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Accepts EITHER an existing user_id (an already-created user with no
     * Driver profile yet — legacy path, rarely applicable now) OR an
     * email/password to create a brand new User account atomically with
     * the Driver profile. The latter is the normal path: drivers don't
     * self-register, so there's no other way for an admin to onboard one.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'email' => 'nullable|required_without:user_id|email|unique:users,email',
            'password' => ['nullable', 'required_without:user_id', 'confirmed', Password::min(8)],
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'address' => 'nullable|string',
            'license_number' => 'required|string|unique:drivers,license_number',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        // On the "link existing account" path, the target must be a driver
        // account that doesn't already have a profile — otherwise you could
        // double-link one user or attach a driver profile to an admin/
        // passenger account (there's no DB unique constraint to catch it).
        if (! empty($validated['user_id'])) {
            $targetUser = User::findOrFail($validated['user_id']);
            if ($targetUser->role !== 'driver') {
                return response()->json(['message' => 'The selected user is not a driver account.'], 422);
            }
            if ($targetUser->driver()->exists()) {
                return response()->json(['message' => 'This user already has a driver profile.'], 422);
            }
        }

        $driver = DB::transaction(function () use ($validated) {
            $userId = $validated['user_id'] ?? null;

            if (! $userId) {
                $user = User::create([
                    'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'driver',
                ]);
                $userId = $user->id;
            }

            return Driver::create([
                'user_id' => $userId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone_number' => $validated['phone_number'],
                'address' => $validated['address'] ?? null,
                'license_number' => $validated['license_number'],
                'hire_date' => $validated['hire_date'] ?? null,
                'status' => $validated['status'],
            ]);
        });

        return response()->json($driver, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $driver = Driver::with('user')->findOrFail($id);

        return $driver;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'phone_number' => 'sometimes|required|string',
            'address' => 'nullable|string',
            'license_number' => 'sometimes|required|string|unique:drivers,license_number,' . $id,
            'hire_date' => 'nullable|date',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $driver = Driver::findOrFail($id);
        $driver->update($validated);

        // Driver.first_name/last_name is the source of truth for this
        // person's name, but the account (User.name, shown in headers etc.)
        // is a separate field — keep it in sync so it doesn't go stale.
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $driver->user->update([
                'name' => $driver->first_name . ' ' . $driver->last_name,
            ]);
        }

        return $driver;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $driver = Driver::findOrFail($id);

        // Block instead of cascade: deleting a driver used to cascade-delete
        // all their trips and every reservation/boarding on them.
        if ($driver->trips()->exists() || $driver->attendances()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this driver while they still have trips or attendance records. Reassign or remove those first.',
            ], 409);
        }

        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully']);
    }
}
