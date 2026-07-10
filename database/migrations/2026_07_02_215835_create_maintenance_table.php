<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->onDelete('cascade');
            $table->enum('maintenance_type',['oil_change','tire_replacement','brake_inspection','engine_repair','transmission_service','electrical_system_check','suspension_inspection']);
            $table->enum('maintenance_status',['scheduled','in_progress','completed'])->default('scheduled');
            $table->string('description')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance');
    }
};
