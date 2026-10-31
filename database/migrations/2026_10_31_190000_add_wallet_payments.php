<?php

use App\Models\Setting;
use App\Support\SiteSettings;
use App\Support\WalletPayments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying with the wallet. An order records how much of it the wallet paid (`wallet_amount`; the rest is real money) and when an
 * order's money was sent back to the wallet (`wallet_refunded_at`, which also keeps it from being refunded twice). A product says
 * whether the wallet may pay for it (`wallet_payable`, off by default). Adds the top-up minimum and the membership switch to /admin/setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'wallet_amount')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('wallet_amount', 10, 2)->default(0)->after('total_paid');
                $table->timestamp('wallet_refunded_at')->nullable()->after('refunded_at');
            });
        }

        if (! Schema::hasColumn('products', 'wallet_payable')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('wallet_payable')->default(false);
            });
        }

        foreach (WalletPayments::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(WalletPayments::seedRows(), 'slug'))->delete();
        SiteSettings::forget();

        if (Schema::hasColumn('orders', 'wallet_amount')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['wallet_amount', 'wallet_refunded_at']));
        }
        if (Schema::hasColumn('products', 'wallet_payable')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('wallet_payable'));
        }
    }
};
