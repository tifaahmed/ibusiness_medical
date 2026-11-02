<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty points as a discount at checkout. A product says whether points may pay for it (`points_payable`, off by default);
 * an order records the points spent (`points_used`) and what they were worth in pounds (`points_amount`, which is taken off
 * what is left to pay exactly like `wallet_amount`). Add-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'points_payable')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('points_payable')->default(false);
            });
        }

        if (! Schema::hasColumn('orders', 'points_used')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedInteger('points_used')->default(0)->after('wallet_amount');
                $table->decimal('points_amount', 10, 2)->default(0)->after('points_used');
            });
        }
    }

    public function down(): void
    {
        // Left in place: an order may already carry points, and dropping the columns would lose that record.
    }
};
