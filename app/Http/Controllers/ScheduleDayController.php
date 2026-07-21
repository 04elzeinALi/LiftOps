<?php

namespace App\Http\Controllers;

use App\Models\ScheduleDay;
use Illuminate\Http\Request;

class ScheduleDayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $scheduleDays = ScheduleDay::with('schedule.route')->paginate(15);

        return $scheduleDays;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $scheduleDay = ScheduleDay::create($validated);

        return response()->json($scheduleDay, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $scheduleDay = ScheduleDay::with('schedule.route')->findOrFail($id);

        return $scheduleDay;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'schedule_id' => 'sometimes|required|exists:schedules,id',
            'day_of_week' => 'sometimes|required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $scheduleDay = ScheduleDay::findOrFail($id);
        $scheduleDay->update($validated);

        return $scheduleDay;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $scheduleDay = ScheduleDay::findOrFail($id);
        $scheduleDay->delete();

        return response()->json(['message' => 'Schedule day deleted successfully']);
    }
}
