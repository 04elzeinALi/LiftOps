<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card is now bought for a segment of a route, not the whole route, because
 * the fare is banded on how far the passenger actually rides — "Khalde to Tyre,
 * monthly" costs more than "Cola to Khalde, monthly".
 *
 * Nullable so cards sold before this keep working: with no segment recorded,
 * pricing falls back to the route's end-to-end distance (see
 * TravelCard::calculatePrice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_cards', function (Blueprint $table) {
            $table->foreignId('from_station_id')->nullable()->after('route_id')
                ->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->after('from_station_id')
                ->constrained('stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('travel_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_station_id');
            $table->dropConstrainedForeignId('to_station_id');
        });
    }
};
