<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archives the loyalty rate (points per 1 EGP) an order was sold at, like order_products archives prices.
 * Only adds: a nullable column, then fills it for old orders from their own figures (points_used / points_amount) where empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'points_rate')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('points_rate', 10, 4)->nullable()->after('points_amount');
            });
        }

        DB::table('orders')
            ->whereNull('points_rate')
            ->where('points_amount', '>', 0)
            ->where('points_used', '>', 0)
            ->update(['points_rate' => DB::raw('ROUND(points_used / points_amount, 4)')]);
    }

    public function down(): void
    {
        // Intentionally empty: never drop archived order data.
    }
};
