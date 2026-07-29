<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trip becomes one leg of a shift — a single run in one direction — instead
 * of an instance of a fixed timetable entry.
 *
 * schedule_id becomes nullable rather than being dropped: trips created under
 * the old timetable still point at it, and their reservations, boardings and
 * payments all hang off them. New trips carry their own times, taken from
 * their shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('id')
                ->constrained('shifts')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number')->nullable()->after('shift_id');
            $table->enum('direction', ['outbound', 'inbound'])->nullable()->after('round_number');
            $table->time('departure_time')->nullable()->after('trip_date');
            $table->time('arrival_time')->nullable()->after('departure_time');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['round_number', 'direction', 'departure_time', 'arrival_time']);
        });
    }
};
