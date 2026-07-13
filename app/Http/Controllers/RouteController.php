<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Route;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $routes = Route::paginate(15);

        return $routes;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_name' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'distance_km' => 'required|numeric',
            'estimated_duration' => 'required|string',
            'fare' => 'required|numeric',
        ]);

        $route = Route::create($validated);

        return response()->json($route, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $route = Route::findOrFail($id);

        return $route;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'route_name' => 'sometimes|required|string',
            'origin' => 'sometimes|required|string',
            'destination' => 'sometimes|required|string',
            'distance_km' => 'sometimes|required|numeric',
            'estimated_duration' => 'sometimes|required|string',
            'fare' => 'sometimes|required|numeric',
        ]);

        $route = Route::findOrFail($id);
        $route->update($validated);

        return $route;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $route = Route::findOrFail($id);
        $route->delete();

        return response()->json(['message' => 'Route deleted successfully']);
    }
}
