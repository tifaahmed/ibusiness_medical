<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What delivery cost, what it was charged at, and the difference.
     *
     * The same three figures `order_products` already archives per line
     * (`cost_price`, `new_price`, `profit_price`), for the one part of an order
     * that is not a product. They are archived rather than derived so a change
     * to the shop's delivery arrangement never rewrites what an old order says
     * it made.
     *
     * `delivery_profit` is stored as well as derivable on purpose: it is a
     * reporting figure, and a report recomputing it from two other archived
     * columns is one more place for the arithmetic to drift.
     *
     * Default zero rather than nullable: every order has a delivery
     * arrangement, and "free delivery" is zero, not "unknown". Orders placed
     * before this migration read as zero, which is what they were charged.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_cost', 10, 2)->default(0)->after('total_amount_before_discount');
            $table->decimal('delivery_price', 10, 2)->default(0)->after('delivery_cost');
            $table->decimal('delivery_profit', 10, 2)->default(0)->after('delivery_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_cost', 'delivery_price', 'delivery_profit']);
        });
    }
};
