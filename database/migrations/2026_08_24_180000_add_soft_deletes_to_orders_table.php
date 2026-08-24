<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give an order a trash to sit in rather than a row to vanish from.
     *
     * An order is the record of money that changed hands: deleting one outright
     * takes its lines with it (`order_products` cascades) and leaves the buyer
     * holding a code nothing answers to. A `deleted_at` keeps the row, its
     * lines and its receipts intact while taking it out of every list, so a
     * mistaken delete is a click away from being undone and a real one is a
     * deliberate second step on the trash page.
     *
     * Indexed because every order query now filters on it.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
