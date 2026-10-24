<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member cannot edit a national ID they already gave: they ask for the change, with the number
     * they want and (optionally) photos of both sides of the ID card, and an admin approves it.
     */
    public function up(): void
    {
        // Safe to run again on a database that already has the table (restored from a backup, or half-migrated).
        if (Schema::hasTable('national_id_change_requests')) {
            return;
        }

        Schema::create('national_id_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_national_id', 14)->nullable();
            $table->string('new_national_id', 14);
            /* Paths on the private disk — never public; admins see them through an authenticated route. */
            $table->string('front_path')->nullable();
            $table->string('back_path')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('contact_message_id')->nullable()->constrained('contact_messages')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_id_change_requests');
    }
};
