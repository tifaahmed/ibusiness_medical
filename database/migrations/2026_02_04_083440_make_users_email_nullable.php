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
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique constraint on email first
            $table->dropUnique(['email']);
            // Make email nullable
            $table->string('email')->nullable()->change();
            // Add unique constraint back but allowing nulls (partial unique index)
            $table->unique('email', 'users_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert changes
            $table->dropUnique('users_email_unique');
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
