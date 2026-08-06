<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sending a bus to maintenance from the Buses page auto-creates a maintenance
// record so the admin doesn't lose track of it (see BusController::update) —
// but at that point the reason isn't known yet, and none of the existing
// types are honest as a default. This adds a catch-all the admin can leave
// as-is or narrow down once they fill in the record's real details.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE maintenance MODIFY maintenance_type ENUM('oil_change','tire_replacement','brake_inspection','engine_repair','transmission_service','electrical_system_check','suspension_inspection','other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE maintenance MODIFY maintenance_type ENUM('oil_change','tire_replacement','brake_inspection','engine_repair','transmission_service','electrical_system_check','suspension_inspection') NOT NULL");
    }
};
