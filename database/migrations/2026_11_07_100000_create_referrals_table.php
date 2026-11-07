<?php

use App\Models\Setting;
use App\Support\Referrals;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member-gets-member. One row per referred member (unique `referred_user_id`): who brought them (by card number), how the code
 * arrived (link / typed), what the referrer was paid on sign-up and on the first paid subscription, and the discount the new member
 * got. Additive only: nothing existing is touched; the money itself lives in `wallet_transactions` (append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referrals')) {
            Schema::create('referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referrer_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
                $table->string('referrer_number', 32);
                $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('source', 16)->default('link');
                $table->decimal('signup_bonus', 10, 2)->default(0);
                $table->decimal('subscription_bonus', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->foreignId('subscription_id')->nullable()->constrained('membership_subscriptions')->nullOnDelete();
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamps();

                $table->index('referrer_user_id');
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
        Schema::dropIfExists('referrals');
        Setting::query()->whereIn('slug', array_column(Referrals::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
