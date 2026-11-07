<?php

use App\Models\Setting;
use App\Support\SiteSettings;
use App\Support\WalletWithdrawals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member cashes out wallet money to Vodafone Cash through Paymob Payouts. Additive only. The wallet is debited when the request is
 * made (so the money cannot be spent twice) and credited back ONCE if the request ends without money leaving (rejected, cancelled,
 * failed). The switch is OFF by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_withdrawals')) {
            Schema::create('wallet_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('method', 24)->default('vodafone_cash');
                $table->string('msisdn', 16);
                $table->text('national_id');                        // encrypted
                $table->string('status', 16)->default('pending');   // pending | processing | paid | failed | rejected | canceled
                $table->uuid('client_reference')->unique();         // sent to Paymob: a retry/inquiry can never double-pay
                $table->string('provider_transaction_id', 64)->nullable();
                $table->string('provider_status', 24)->nullable();
                $table->string('provider_code', 16)->nullable();
                $table->text('provider_message')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('refunded_at')->nullable();       // set once when the wallet got the money back
                $table->json('events')->nullable();                 // who did what, when (never secrets)
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index('status');
            });
        }

        foreach (WalletWithdrawals::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
        SiteSettings::forget();
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
    }
};
