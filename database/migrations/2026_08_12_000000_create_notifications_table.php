<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// In-app messages addressed to one person — currently "the trip you booked
// was cancelled" and its driver-side counterpart, sent when an admin cancels
// a shift (see ShiftController).
//
// The message is stored as finished text rather than as a reference to the
// trip it's about, deliberately: a notification is a record of what someone
// was told at a point in time, and it has to keep reading correctly after
// the trip, shift or route it describes has been edited or deleted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // What kind of event this was, for grouping/iconography later.
            $table->string('type', 40);
            $table->string('title');
            $table->text('body');
            // Null until the recipient has seen it — this is what the unread
            // badge counts.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The only query this table serves: one person's list, newest
            // first, and their unread count.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
