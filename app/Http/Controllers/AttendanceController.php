<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Driver;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'passenger') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Attendance::with(['driver', 'trip']);

        if ($user->role === 'driver') {
            $query->whereHas('driver', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'passenger') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'trip_id' => 'nullable|exists:trips,id',
            'check_in' => 'required|date',
            'check_out' => 'nullable|date',
            'status' => 'required|in:present,absent,late',
        ]);

        // A driver can only check themselves in/out.
        if ($user->role === 'driver') {
            $ownDriver = Driver::where('user_id', $user->id)->firstOrFail();
            $validated['driver_id'] = $ownDriver->id;
        }

        $attendance = Attendance::create($validated);

        return response()->json($attendance, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $attendance = Attendance::with(['driver', 'trip'])->findOrFail($id);

        $this->authorizeOwnership($request, $attendance);

        return $attendance;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $attendance = Attendance::findOrFail($id);

        $this->authorizeOwnership($request, $attendance);

        $validated = $request->validate([
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'trip_id' => 'nullable|exists:trips,id',
            'check_in' => 'sometimes|required|date',
            'check_out' => 'nullable|date',
            'status' => 'sometimes|required|in:present,absent,late',
        ]);

        $attendance->update($validated);

        return $attendance;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $attendance = Attendance::findOrFail($id);

        $this->authorizeOwnership($request, $attendance);

        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

    /**
     * Admins may act on any attendance record; a driver may only act on
     * their own; passengers are blocked.
     */
    private function authorizeOwnership(Request $request, Attendance $attendance): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'driver' && $attendance->driver?->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
