<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three independent switches for what a product may do on the storefront.
 *
 * Deliberately three columns rather than one status: they answer different
 * questions and are used in combination. A product can be listed but not
 * openable (a teaser), openable but not listed (an unlisted link handed to one
 * customer), or readable but not sellable (out of stock, price under review).
 *
 * Everything defaults to true so the whole existing catalogue keeps behaving
 * exactly as it did before these columns existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /* Shown in the shop's listing, search and related rails. */
            $table->boolean('is_visible')->default(true)->after('product_type_id');
            /* Its own page can be opened. */
            $table->boolean('is_accessible')->default(true)->after('is_visible');
            /* It can be put in a basket and ordered. */
            $table->boolean('is_purchasable')->default(true)->after('is_accessible');

            /* The listing filters on visibility on every catalogue request. */
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_visible']);
            $table->dropColumn(['is_visible', 'is_accessible', 'is_purchasable']);
        });
    }
};
