<?php

use App\Models\Setting;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service trial cards and paid subscriptions.
 *
 * `trial_claims` is a durable ledger, deliberately apart from `memberships`:
 * a phone number and a national ID may take ONE trial each, ever. The unique
 * indexes live here because `memberships.national_id` cannot carry one (the
 * imported data already holds duplicates), and because deleting a membership
 * must not hand its owner a second free month.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a re-run after a partial or hand-applied change is harmless.
        if (! Schema::hasColumn('memberships', 'plan')) {
            Schema::table('memberships', function (Blueprint $table) {
                /* `trial` = a free, self-issued card with an expiry; `full` = everything else (all existing rows). */
                $table->string('plan', 16)->default('full')->after('is_paid')->index();
            });
        }
        if (! Schema::hasColumn('memberships', 'source')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->string('source', 32)->nullable()->after('plan');
            });
        }
        if (! Schema::hasColumn('memberships', 'trial_started_at')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->timestamp('trial_started_at')->nullable()->after('source');
            });
        }

        if (! Schema::hasTable('trial_claims')) {
            Schema::create('trial_claims', function (Blueprint $table) {
                $table->id();
                $table->char('national_id', 14)->unique();
                /* National form, 01xxxxxxxxx. */
                $table->string('phone', 20)->unique();
                $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('membership_subscriptions')) {
            Schema::create('membership_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
                /* paymob | cash_on_delivery | vodafone_cash | instapay */
                $table->string('method', 24)->index();
                /* pending | accepted | rejected | refunded | canceled */
                $table->string('status', 16)->default('pending')->index();
                $table->decimal('amount', 10, 2);
                $table->unsignedSmallInteger('months');
                /* What the member typed with a transfer (sender wallet / instapay handle / reference number). */
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->string('email')->nullable();
                $table->unsignedInteger('paymob_attempts')->default(0);
                $table->string('paymob_reference')->nullable();
                $table->string('paymob_order_id')->nullable();
                $table->string('paymob_transaction_id')->nullable()->index();
                $table->string('paymob_response_code', 64)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->timestamp('receipt_emailed_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('order_payment_logs', 'membership_subscription_id')) {
            Schema::table('order_payment_logs', function (Blueprint $table) {
                $table->foreignId('membership_subscription_id')->nullable()->after('order_id')
                    ->constrained('membership_subscriptions')->nullOnDelete();
            });
        }

        foreach (MembershipPlan::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_payment_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_subscription_id');
        });
        Schema::dropIfExists('membership_subscriptions');
        Schema::dropIfExists('trial_claims');
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['plan', 'source', 'trial_started_at']);
        });
        Setting::query()->whereIn('slug', array_column(MembershipPlan::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
