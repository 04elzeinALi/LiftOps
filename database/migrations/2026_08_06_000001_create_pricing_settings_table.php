<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A single-row settings table, not a per-route column: the distance band is
// a network-wide policy (see Route::fareForKm()), so one place to change it
// is what lets an admin react to something like a fuel price jump without
// editing every route. Seeded with the values that used to be hardcoded
// constants (Route::LONG_TRIP_KM / SHORT_TRIP_FARE / LONG_TRIP_FARE) so
// existing fares don't move on migrate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('long_trip_km', 8, 2);
            $table->decimal('short_trip_fare', 8, 2);
            $table->decimal('long_trip_fare', 8, 2);
            $table->timestamps();
        });

        DB::table('pricing_settings')->insert([
            'long_trip_km' => 40,
            'short_trip_fare' => 2.00,
            'long_trip_fare' => 3.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
