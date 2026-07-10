<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bus;

class BusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
          $buses = Bus::all();

          return $buses;

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $bus = Bus::create([
            'plate_number' => $request->plate_number,
            'manufacturer' => $request->manufacturer,
            'model' => $request->model,
            'production_year' => $request->production_year,
            'capacity' => $request->capacity,
            'status' => $request->status,

        ]);
        return $bus;
    }


    /**w
     * Display the specified resource.
     */


    public function show(string $id)
    {
        $bus = Bus::find($id);
        return $bus;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
