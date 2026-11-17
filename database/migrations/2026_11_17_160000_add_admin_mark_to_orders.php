<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An admin's private mark on an order row: `done` (finished, real money — do not reopen) or `test` (a test order). Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('admin_mark', 10)->nullable()->after('relative_fee_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('admin_mark');
        });
    }
};
