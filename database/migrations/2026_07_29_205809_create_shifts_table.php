<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shift is one driver's working day on one route: they start at the origin
 * at start_time and keep running the line until end_time. Exact per-stop
 * timetables aren't realistic on this corridor, so a shift only fixes when the
 * driver clocks on and off, and the individual legs are spread evenly between
 * those two times.
 *
 * A "round" is one out-and-back (origin -> terminus -> origin), so a shift of
 * `rounds` rounds runs rounds * 2 legs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('bus_id')->constrained('buses')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('rounds')->default(2);
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled', 'emergency'])
                ->default('scheduled');
            $table->timestamps();

            // A driver works one shift per day; the UI and the generator both
            // rely on that being true.
            $table->unique(['driver_id', 'shift_date']);
            $table->index(['shift_date', 'route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
