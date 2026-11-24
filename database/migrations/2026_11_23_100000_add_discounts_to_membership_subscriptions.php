<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Loyalty points and wallet money spent on a membership payment (a press by the member, never automatic). `amount` stays
 * what is left to pay in REAL money; these columns keep what was taken off, and `discounts_returned_at` makes the give-back
 * (a rejected / cancelled / refunded payment) happen once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('membership_subscriptions', 'points_used')) {
            return;
        }

        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('points_used')->default(0)->after('amount');
            $table->decimal('points_amount', 10, 2)->default(0)->after('points_used');
            $table->decimal('wallet_amount', 10, 2)->default(0)->after('points_amount');
            $table->timestamp('discounts_returned_at')->nullable()->after('wallet_amount');
        });
    }

    public function down(): void
    {
        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['points_used', 'points_amount', 'wallet_amount', 'discounts_returned_at']);
        });
    }
};
