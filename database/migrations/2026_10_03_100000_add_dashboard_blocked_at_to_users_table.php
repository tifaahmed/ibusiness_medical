<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When set, the account keeps its login but is refused at the admin area's door. */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'dashboard_blocked_at')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dashboard_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_blocked_at');
        });
    }
};
