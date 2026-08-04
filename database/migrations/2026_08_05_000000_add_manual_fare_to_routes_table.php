<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Null means "priced automatically from distance" (see Route::fareBetweenStations
// / TravelCard::baseFare) — this column only overrides that when an admin sets it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('manual_fare', 8, 2)->nullable()->after('fare');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('manual_fare');
        });
    }
};
