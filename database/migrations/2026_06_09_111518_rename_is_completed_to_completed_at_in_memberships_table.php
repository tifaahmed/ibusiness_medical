<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable()->after('is_visible');
        });

        // Backfill: completed members get their registration_date; incomplete stay null
        DB::statement('UPDATE memberships SET completed_at = registration_date WHERE is_completed = 1');
        DB::statement('UPDATE memberships SET completed_at = NULL WHERE is_completed = 0');

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['is_completed']);
            $table->dropColumn('is_completed');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('is_visible');
            $table->index('is_completed');
        });

        // Restore is_completed based on whether completed_at is set
        DB::statement('UPDATE memberships SET is_completed = 1 WHERE completed_at IS NOT NULL');
        DB::statement('UPDATE memberships SET is_completed = 0 WHERE completed_at IS NULL');

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
