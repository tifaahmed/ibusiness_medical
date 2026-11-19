<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A buyer may hide an order they no longer want while nothing has happened to it. The order is never deleted: only this stamp is set. ADD ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'hidden_by_customer_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('hidden_by_customer_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop columns that hold data.
    }
};
