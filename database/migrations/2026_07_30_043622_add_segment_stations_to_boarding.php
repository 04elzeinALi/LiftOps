<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a rider actually got on and off, recorded per boarding.
 *
 * The driver's manifest shows this ("Sarafand -> Tyre"), and for a walk-up the
 * driver picks it on the spot, since it's what the fare is banded on. A rider
 * boarding against a reservation inherits it from their travel card, so these
 * are nullable and filled in by BoardingController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding', function (Blueprint $table) {
            $table->foreignId('from_station_id')->nullable()->after('travel_card_id')
                ->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->after('from_station_id')
                ->constrained('stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boarding', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_station_id');
            $table->dropConstrainedForeignId('to_station_id');
        });
    }
};
