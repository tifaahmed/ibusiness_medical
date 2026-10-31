<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a member adds to their wallet from the app, paid online through Paymob. The wallet is only credited when a SIGNED callback
     * says the right amount arrived (`ApplyPaymobTopupTransaction`), once. References sent to Paymob are `TOP<id>A<attempt>`.
     */
    public function up(): void
    {
        if (! Schema::hasTable('wallet_topups')) {
            Schema::create('wallet_topups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('status', 12)->default('pending')->index();
                $table->unsignedSmallInteger('paymob_attempts')->default(0);
                $table->string('paymob_reference', 64)->nullable();
                $table->string('paymob_order_id', 64)->nullable();
                $table->string('paymob_transaction_id', 64)->nullable();
                $table->string('paymob_response_code', 64)->nullable();
                $table->string('channel', 16)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('order_payment_logs') && ! Schema::hasColumn('order_payment_logs', 'wallet_topup_id')) {
            Schema::table('order_payment_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_topup_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_payment_logs', 'wallet_topup_id')) {
            Schema::table('order_payment_logs', fn (Blueprint $table) => $table->dropColumn('wallet_topup_id'));
        }
        Schema::dropIfExists('wallet_topups');
    }
};
