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
            $table->boolean('is_completed')->default(false)->after('is_visible');
            $table->index('is_completed');
        });

        // Backfill existing memberships as completed (this is a live DB —
        // all rows that existed before this migration are considered done).
        DB::table('memberships')->update(['is_completed' => true]);
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['is_completed']);
            $table->dropColumn('is_completed');
        });
    }
};
