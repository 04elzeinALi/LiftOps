<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A separate, deliberately loose table from `boarding` — a cash customer has
// no account, no travel card, and rides once. Forcing that through `boarding`
// would mean making passenger_id/travel_card_id nullable on a table whose
// whole shape assumes a real passenger + card behind every row (see
// BoardingController's capacity/remaining-trips math, which leans on both
// being present). Keeping this separate keeps that table's guarantees intact
// at the cost of cash revenue living in its own place rather than folded
// into the Payments report — see the controller's docblock.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_boardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('customer_name');
            // Where they got on and off. Nullable so a driver in a hurry can
            // still record the fare and the name without stopping to pick
            // stations — the money is the part that must not be lost.
            $table->foreignId('from_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->decimal('amount', 8, 2);
            $table->dateTime('boarded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_boardings');
    }
};
