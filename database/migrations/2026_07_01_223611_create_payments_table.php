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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_card_id')->constrained('travel_cards')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method',['credit_card','wish','cash','bank_transfer'])->default('cash');
            $table->enum('payment_status',['unpaid','paid','failed'])->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
