<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-route pricing bands. Each route can set its own distance threshold and
// its own two fares, so a short city line and a long coastal one don't have
// to share one policy.
//
// Nullable on purpose: null means "use the network default" from
// pricing_settings, which is what every existing route keeps doing after
// this migration — so nothing reprices on deploy, and the Pricing settings
// page stays meaningful as the default for routes that haven't been given
// their own. See Route::fareForKm() for the resolution order.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('long_trip_km', 8, 2)->nullable()->after('manual_fare');
            $table->decimal('short_trip_fare', 8, 2)->nullable()->after('long_trip_km');
            $table->decimal('long_trip_fare', 8, 2)->nullable()->after('short_trip_fare');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['long_trip_km', 'short_trip_fare', 'long_trip_fare']);
        });
    }
};
