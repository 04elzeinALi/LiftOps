<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attendance = Attendance::with(['driver', 'trip'])->paginate(15);

        return $attendance;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'trip_id' => 'nullable|exists:trips,id',
            'check_in' => 'required|date',
            'check_out' => 'nullable|date',
            'status' => 'required|in:present,absent,late',
        ]);

        $attendance = Attendance::create($validated);

        return response()->json($attendance, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $attendance = Attendance::with(['driver', 'trip'])->findOrFail($id);

        return $attendance;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'trip_id' => 'nullable|exists:trips,id',
            'check_in' => 'sometimes|required|date',
            'check_out' => 'nullable|date',
            'status' => 'sometimes|required|in:present,absent,late',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update($validated);

        return $attendance;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }
}
