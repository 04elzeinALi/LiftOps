<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Searching is done here rather than in the browser because the list is
     * paginated: filtering the current page client-side would hide every
     * match that happens to live on page 2.
     */
    public function index(Request $request)
    {
        $query = Station::query();

        if ($request->filled('search')) {
            $term = $request->query('search');
            // Escape the LIKE wildcards so a station name containing % or _
            // is searched for literally instead of matching everything.
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
            $query->where('station_name', 'like', "%{$escaped}%");
        }

        return $query->orderBy('station_name')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_name' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $station = Station::create($validated);

        return response()->json($station, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $station = Station::findOrFail($id);

        return $station;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'station_name' => 'sometimes|required|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
        ]);

        $station = Station::findOrFail($id);
        $station->update($validated);

        return $station;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $station = Station::findOrFail($id);
        $station->delete();

        return response()->json(['message' => 'Station deleted successfully']);
    }
}
