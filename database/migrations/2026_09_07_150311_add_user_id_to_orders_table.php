<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who placed an order, when it was somebody signed in.
 *
 * The storefront had no accounts when orders were built, so the order CODE was
 * the only key to one and `membership_number` was the only trace of a member on
 * it. Now that a member can sign in with their phone, an order can belong to
 * somebody, and their order history stops depending on the browser that placed
 * it.
 *
 * Nullable, and it stays that way: guest checkout is not going anywhere, and
 * every order already in the table was placed by nobody in particular. Deleting
 * a member must not delete their orders either — the sale happened — so the
 * column is nulled rather than cascaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('order_code')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
