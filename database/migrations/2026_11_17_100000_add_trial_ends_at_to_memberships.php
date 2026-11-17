<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day a trial ends, apart from the membership's own expiry (`trial_ends_at` <= `expiration_date`).
 * ADD-ONLY: one nullable column, no data touched. NULL = the trial ends with the membership, as before.
 * Once the trial date has passed and the membership still runs, the member is a normal paid member.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('memberships', 'trial_ends_at')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->date('trial_ends_at')->nullable()->after('trial_started_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop a column that may hold data.
    }
};
