<?php

use App\Models\Setting;
use App\Support\LoyaltyPoints;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caps on loyalty points at checkout: per order and per product (system settings) + a product's own override. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'points_max')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedInteger('points_max')->nullable()->after('points_payable'); // null = follow the system cap
            });
        }

        foreach (LoyaltyPoints::seedRows() as $row) {
            if (in_array($row['slug'], [LoyaltyPoints::MAX_PER_ORDER, LoyaltyPoints::MAX_PER_PRODUCT], true) && ! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
        SiteSettings::forget();
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'points_max')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('points_max'));
        }
        Setting::query()->whereIn('slug', [LoyaltyPoints::MAX_PER_ORDER, LoyaltyPoints::MAX_PER_PRODUCT])->delete();
        SiteSettings::forget();
    }
};
