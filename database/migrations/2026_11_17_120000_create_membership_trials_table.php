<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's history with free trials, one row per event: started / extended / shortened / ended.
 * APPEND-ONLY and ADD-ONLY: a new table, nothing in it is ever edited or deleted by the app. It answers
 * "how many times was this customer extended, from which date to which, and who did it".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_trials')) {
            return;
        }

        Schema::create('membership_trials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_id')->index();
            $table->string('event', 16);                    // started | extended | shortened | ended
            $table->date('starts_on')->nullable();          // when the trial began
            $table->date('ends_on')->nullable();            // the trial end date AFTER this event
            $table->date('previous_ends_on')->nullable();   // ... and before it (null on `started`)
            $table->date('expires_on')->nullable();         // the membership's own expiry AFTER this event
            $table->date('previous_expires_on')->nullable();
            $table->unsignedSmallInteger('days')->nullable(); // starts_on -> ends_on, in days
            $table->unsignedBigInteger('admin_id')->nullable(); // null = the member themself / a system job
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Intentionally empty: never drop a table that may hold history.
    }
};
