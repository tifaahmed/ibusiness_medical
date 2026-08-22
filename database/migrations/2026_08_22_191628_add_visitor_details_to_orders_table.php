<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a storefront order carries that an admin-entered one does not:
     * where to deliver it, and where it came from.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('customer_address')->nullable()->after('customer_phone');
            $table->text('notes')->nullable()->after('customer_address');

            /*
             * The BUYER's address, not the caller's. Orders arrive from the
             * Deilar storefront over the partner API, so `$request->ip()` here
             * is that server — the visitor's own address is forwarded in the
             * request body, which is why the endpoint is key-gated.
             *
             * 45 characters: an IPv4-mapped IPv6 address is the longest form
             * that turns up in practice.
             */
            $table->string('ip_address', 45)->nullable()->after('cancel_reason');
            $table->text('user_agent')->nullable()->after('ip_address');

            /*
             * Which property took the order. One column now, but orders will
             * not only ever come from one storefront, and back-filling a
             * source onto rows that never recorded one is guesswork.
             */
            $table->string('source', 32)->nullable()->after('user_agent');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn([
                'customer_address',
                'notes',
                'ip_address',
                'user_agent',
                'source',
            ]);
        });
    }
};
