<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schedules = Schedule::with('route')->paginate(15);

        return $schedules;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required',
            'arrival_time' => 'required',
        ]);

        $schedule = Schedule::create($validated);

        return response()->json($schedule, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $schedule = Schedule::with('route')->findOrFail($id);

        return $schedule;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'route_id' => 'sometimes|required|exists:routes,id',
            'departure_time' => 'sometimes|required',
            'arrival_time' => 'sometimes|required',
        ]);

        $schedule = Schedule::findOrFail($id);
        $schedule->update($validated);

        return $schedule;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
