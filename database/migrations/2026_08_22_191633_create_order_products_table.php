<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The lines of an order, each one a snapshot of the product as it was sold.
     *
     * Every field a receipt needs is copied here rather than read back through
     * `product_id`: prices change, products are renamed, photographs are
     * replaced and catalogue rows get deleted. An order that reads its own
     * totals out of today's `products` table is an order that silently rewrites
     * itself, and a deleted product would take the line with it.
     *
     * `product_id` is kept anyway, nullable, so an admin can still jump from a
     * line to the catalogue row it came from while that row exists.
     */
    public function up(): void
    {
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            /*
             * The whole translation map, not the one language the buyer
             * happened to be reading: the admin reading this order back may be
             * working in the other one.
             */
            $table->json('name');
            $table->string('slug')->nullable();
            $table->text('image')->nullable();

            $table->unsignedInteger('quantity');

            /** The struck-through price, when this line was sold at a markdown. */
            $table->decimal('old_price', 10, 2)->nullable();
            /** The selling price on the day — what one unit cost the buyer. */
            $table->decimal('new_price', 10, 2)->nullable();
            /** `new_price * quantity`, stored so a total cannot drift from its lines. */
            $table->decimal('line_total', 10, 2);

            /*
             * What the shop paid and what it made. Copied for the same reason
             * as the prices: margin is reported per order, and re-deriving it
             * from today's cost turns last month's report into a different
             * number every time it is run.
             */
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('profit_price', 10, 2)->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
