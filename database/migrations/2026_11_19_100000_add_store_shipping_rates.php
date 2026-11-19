<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a store charges to ship, and the order total above which it ships free.
 * ADD ONLY: two nullable columns (the figure for every governorate) and a new
 * table of per-governorate overrides. NULL = "not set" / "follow the store's
 * own figure"; 0 is a real answer (free).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'shipping_price')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('shipping_price', 10, 2)->nullable();
                $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            });
        }

        if (! Schema::hasTable('store_shipping_rates')) {
            Schema::create('store_shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('free_threshold', 10, 2)->nullable();
                $table->timestamps();
                $table->unique(['store_id', 'governorate_id']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop columns or tables that hold data.
    }
};
