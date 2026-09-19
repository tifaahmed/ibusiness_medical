<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Google Maps link copied from the browser is routinely 500-700
     * characters, and the forms accept up to 2048 — but the column was a
     * VARCHAR(255), so saving one failed with "Data too long".
     */
    public function up(): void
    {
        foreach (['facility_branches', 'store_branches'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('google_location_url')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['facility_branches', 'store_branches'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('google_location_url')->nullable()->change();
            });
        }
    }
};
