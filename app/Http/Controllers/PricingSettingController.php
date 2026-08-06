<?php

namespace App\Http\Controllers;

use App\Models\PricingSetting;
use Illuminate\Http\Request;

class PricingSettingController extends Controller
{
    /**
     * The current network-wide pricing settings. Readable by any
     * authenticated user (not just admins) — drivers and passengers need
     * these numbers to show a fare preview that matches what they'll
     * actually be charged (see the frontend's lib/fare.js).
     */
    public function show()
    {
        return PricingSetting::current();
    }

    /**
     * Update the network-wide DEFAULT pricing. Admin-only (see routes/api.php).
     *
     * This is the fallback, not the last word: Route::fareForKm() prefers a
     * route's own distance bands where it has them, and a route's manual_fare
     * bypasses distance banding altogether. Changing these values therefore
     * only moves routes that haven't been given pricing of their own.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'long_trip_km' => 'required|numeric|min:0.1',
            'short_trip_fare' => 'required|numeric|min:0',
            'long_trip_fare' => 'required|numeric|min:0',
        ]);

        $settings = PricingSetting::current();
        $settings->update($validated);

        return $settings;
    }
}
