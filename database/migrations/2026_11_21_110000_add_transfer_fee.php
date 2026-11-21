<?php

use App\Models\Setting;
use App\Support\MemberTransfers;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transfer between members costs a fee (default 50%): the sender gives `amount`, the recipient gets `amount - fee`. The fee is fixed
 * when the request is made and kept on the row (`fee`), so changing the admin setting never changes a request that is already waiting.
 * Older rows keep fee = 0 (they moved the whole amount). Only adds: nothing is dropped or rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transfers') && ! Schema::hasColumn('wallet_transfers', 'fee')) {
            Schema::table('wallet_transfers', function (Blueprint $table): void {
                $table->decimal('fee', 14, 2)->default(0)->after('amount');
            });
        }

        foreach (MemberTransfers::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        if (Schema::hasColumn('wallet_transfers', 'fee')) {
            Schema::table('wallet_transfers', fn (Blueprint $table) => $table->dropColumn('fee'));
        }
        Setting::query()->where('slug', MemberTransfers::FEE_PERCENT)->delete();
        SiteSettings::forget();
    }
};
