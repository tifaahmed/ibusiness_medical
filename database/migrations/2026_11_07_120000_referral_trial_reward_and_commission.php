<?php

use App\Models\Setting;
use App\Support\Referrals;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral round 3 (additive only). The referrer is paid (1) a flat reward when the new member STARTS A TRIAL, and (2) a percentage
 * commission on EVERY successful subscription payment of that member (renewals included). Every payout is one row of
 * `referral_rewards` (the audit ledger) tied to one `wallet_transactions` row; a refund writes a reversal, never an edit.
 * Nothing existing is dropped: the older `signup_bonus` / `subscription_bonus` columns stay as history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            if (! Schema::hasColumn('referrals', 'trial_started_at')) {
                $table->timestamp('trial_started_at')->nullable()->after('discount_amount');
                $table->timestamp('trial_rewarded_at')->nullable()->after('trial_started_at');
                $table->decimal('trial_bonus', 10, 2)->default(0)->after('trial_rewarded_at');
            }
        });

        if (! Schema::hasTable('referral_rewards')) {
            Schema::create('referral_rewards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
                $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 16); // trial | commission
                /* `trial:<referral id>` or `commission:<subscription id>` — unique: one payout per trial / per payment, ever. */
                $table->string('reward_key', 64)->unique();
                $table->foreignId('subscription_id')->nullable()->constrained('membership_subscriptions')->nullOnDelete();
                $table->string('plan', 16)->nullable();            // year | half | other (what the paid subscription was)
                $table->decimal('base_amount', 10, 2)->default(0); // what the member really paid
                $table->decimal('rate', 6, 2)->default(0);         // commission percent at that time
                $table->decimal('amount', 10, 2)->default(0);      // pounds credited to the referrer
                $table->string('status', 12)->default('paid');     // paid | reversed
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 120)->nullable();
                $table->string('note', 190)->nullable();
                $table->timestamps();

                $table->index(['referrer_user_id', 'created_at']);
                $table->index('referral_id');
            });
        }

        foreach (Referrals::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
        SiteSettings::forget();
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        Schema::table('referrals', function (Blueprint $table) {
            foreach (['trial_bonus', 'trial_rewarded_at', 'trial_started_at'] as $column) {
                if (Schema::hasColumn('referrals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Setting::query()->where('slug', Referrals::COMMISSION_PERCENT)->delete();
        SiteSettings::forget();
    }
};
