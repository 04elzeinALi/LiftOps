<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// routes.fare is no longer collected or read. It predates per-route pricing:
// back when a route had one flat price, this was it. Now every route carries
// its own distance bands (long_trip_km / short_trip_fare / long_trip_fare)
// plus an optional manual_fare, and Route::fareForKm() answers from those —
// so the admin form stopped asking for this and nothing reads it any more
// (the last reader, TravelCard::baseFare()'s no-stops fallback, now prices
// through the route's own bands instead).
//
// Made nullable rather than dropped: existing rows keep whatever they were
// sold at, which is worth having if anyone ever needs to audit an old price.
// Dropping the column is a separate decision once that history stops mattering.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE routes MODIFY fare DECIMAL(8,2) NULL');
    }

    public function down(): void
    {
        // Anything created after this migration has no fare; give those rows a
        // value so the column can be NOT NULL again.
        DB::statement('UPDATE routes SET fare = 0 WHERE fare IS NULL');
        DB::statement('ALTER TABLE routes MODIFY fare DECIMAL(8,2) NOT NULL');
    }
};
