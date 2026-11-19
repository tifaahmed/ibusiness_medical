<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order that buys from several stores is shipped (and charged for shipping)
 * store by store. ADD ONLY: the line archive learns which store sold it, and a
 * new table keeps one row per store per order. `orders.delivery_*` stay the
 * order's totals (the sum of its shipments), so nothing that reads them moves.
 * Old orders have no shipments and no store on their lines — they read as they
 * always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_products', 'store_id')) {
            Schema::table('order_products', function (Blueprint $table) {
                // An archive, not a relation: no foreign key, so deleting a store never touches a placed order.
                $table->unsignedBigInteger('store_id')->nullable()->after('product_id');
                $table->json('store_name')->nullable()->after('store_id');
            });
        }

        // Stores that share a warehouse are one shipping group: one parcel, one charge.
        if (! Schema::hasTable('shipping_groups')) {
            Schema::create('shipping_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('free_threshold', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('stores', 'shipping_group_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->unsignedBigInteger('shipping_group_id')->nullable();
            });
        }

        // Site-wide "everything ships as one parcel at one price" switch (off by default).
        foreach ([
            ['shop_single_shipping_enabled', ['en' => 'One shipping price for the whole order (all stores, all governorates)', 'ar' => 'سعر شحن واحد للطلب كله (كل المتاجر وكل المحافظات)'], '0', 'boolean'],
            ['shop_single_shipping_price', ['en' => 'The one shipping price (EGP)', 'ar' => 'سعر الشحن الواحد (ج.م)'], '0', 'number'],
            ['shop_single_shipping_free_from', ['en' => 'Free when the order is at least (EGP, empty = never)', 'ar' => 'مجاني لو الطلب من (ج.م، فارغ = لا يوجد)'], '', 'number'],
        ] as [$slug, $name, $value, $type]) {
            if (! \App\Models\Setting::query()->where('slug', $slug)->exists()) {
                \App\Models\Setting::query()->create(['slug' => $slug, 'name' => $name, 'value' => $value, 'value_type' => $type]);
            }
        }

        if (! Schema::hasTable('order_shipments')) {
            Schema::create('order_shipments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->json('store_name')->nullable();
                // Every store whose lines travel in this parcel (several when they share a warehouse / one-shipping is on).
                $table->json('store_ids')->nullable();
                $table->unsignedBigInteger('shipping_group_id')->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('delivery_cost', 10, 2)->default(0);
                $table->decimal('delivery_price', 10, 2)->default(0);
                $table->decimal('delivery_profit', 10, 2)->default(0);
                $table->decimal('free_delivery_threshold', 10, 2)->nullable();
                $table->string('delivery_free_reason')->nullable();
                // 'store' | 'group' | 'single' | 'shop'.
                $table->string('price_source', 16)->default('shop');
                $table->timestamps();
                $table->index(['order_id', 'store_id']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop columns or tables that hold data.
    }
};
