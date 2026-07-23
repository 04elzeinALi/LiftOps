<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'address' => 'nullable|string',
            'license_number' => 'required|string|unique:drivers,license_number',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $driver = Driver::create($validated);

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
        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully']);
    }
}
