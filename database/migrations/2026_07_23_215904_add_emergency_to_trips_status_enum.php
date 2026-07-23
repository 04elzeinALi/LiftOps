<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// doctrine/dbal isn't installed, so enum changes go through raw SQL
// (Laravel's fluent ->change() needs it for column modification).
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'emergency') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE trips SET status = 'cancelled' WHERE status = 'emergency'");
        DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
