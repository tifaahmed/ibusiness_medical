<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why an order was not charged for delivery.
 *
 * `delivery_price` has always been able to read 0, and until now a zero there
 * was ambiguous: a shop that charges nothing for delivery, an order placed
 * before delivery was charged for at all, and a basket that earned free
 * delivery all looked identical on the row. Somebody reading an order in the
 * admin could not tell which, and neither could a report.
 *
 * Two columns settle it. `delivery_free_reason` names why the charge was
 * dropped, and `free_delivery_threshold` records the basket total the
 * storefront said would earn it — the figure an administrator set over in
 * Deilar's page editor, archived onto the order the way every other price is,
 * so changing the setting later does not rewrite what an old order says.
 *
 * Both nullable, and null on every order that predates this. An order with no
 * reason was simply charged whatever `delivery_price` says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
             * A short machine-readable slug, not a sentence: it is read by the
             * admin screens, which render it in the reader's own language.
             * Today there is one value — `order_total_reached_threshold` — and
             * the column exists so the next reason does not need a migration.
             */
            $table->string('delivery_free_reason', 64)
                ->nullable()
                ->after('delivery_profit');

            $table->decimal('free_delivery_threshold', 10, 2)
                ->nullable()
                ->after('delivery_free_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_free_reason', 'free_delivery_threshold']);
        });
    }
};
