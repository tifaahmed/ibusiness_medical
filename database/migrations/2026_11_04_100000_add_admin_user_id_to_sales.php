<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales rep can be tied to ONE admin account, and an admin account to ONE
 * rep: the UNIQUE index makes the database refuse a second link whatever path
 * writes it. Additive only (nullable column); guarded so a re-run is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'admin_user_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('admin_user_id')->nullable()->unique()
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop a column that may hold data.
    }
};
