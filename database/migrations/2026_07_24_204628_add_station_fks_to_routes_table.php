<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nullable, not required: existing routes' origin/destination text was
// free-typed or seeded with fake data that mostly won't match any real
// station name. The backfill below links what it can; anything left
// unmatched keeps showing its frozen text via the Route model's accessor
// fallback (see Route::origin()/destination()) — no data loss, no forced
// renaming of unrelated seed data.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('origin_station_id')->nullable()->after('destination')->constrained('stations')->nullOnDelete();
            $table->foreignId('destination_station_id')->nullable()->after('origin_station_id')->constrained('stations')->nullOnDelete();
        });

        // Backfill: link any route whose origin/destination text exactly
        // matches a current station name (case-insensitive).
        DB::statement('
            UPDATE routes
            JOIN stations ON LOWER(stations.station_name) = LOWER(routes.origin)
            SET routes.origin_station_id = stations.id
        ');

        DB::statement('
            UPDATE routes
            JOIN stations ON LOWER(stations.station_name) = LOWER(routes.destination)
            SET routes.destination_station_id = stations.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['origin_station_id']);
            $table->dropForeign(['destination_station_id']);
            $table->dropColumn(['origin_station_id', 'destination_station_id']);
        });
    }
};
