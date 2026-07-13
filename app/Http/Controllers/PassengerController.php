<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $passengers = Passenger::with('user')->paginate(15);

        return $passengers;
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
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $passenger = Passenger::create($validated);

        return response()->json($passenger, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $passenger = Passenger::with('user')->findOrFail($id);

        return $passenger;
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
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $passenger = Passenger::findOrFail($id);
        $passenger->update($validated);

        return $passenger;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $passenger = Passenger::findOrFail($id);
        $passenger->delete();

        return response()->json(['message' => "Passenger's data deleted successfully"]);
    }
}
