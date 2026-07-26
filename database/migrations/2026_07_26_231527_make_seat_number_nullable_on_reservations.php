<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookings no longer assign a seat number — a reservation just holds a
     * spot, capped by bus capacity. Existing rows keep whatever they had;
     * new bookings leave this null.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('seat_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('seat_number')->nullable(false)->change();
        });
    }
};
