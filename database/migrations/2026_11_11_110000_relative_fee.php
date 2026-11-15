<?php

use App\Models\Setting;
use App\Support\RelativeFee;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Relative fee settings (5 EGP a month, 12 months) and the link from a relative to the order that pays for it. Only adds. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (RelativeFee::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();

        if (! Schema::hasColumn('memberships', 'fee_order_id')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->unsignedBigInteger('fee_order_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('memberships', 'fee_order_id')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->dropColumn('fee_order_id');
            });
        }

        Setting::query()->whereIn('slug', array_column(RelativeFee::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
