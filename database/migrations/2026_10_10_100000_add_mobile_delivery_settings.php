<?php

use App\Models\Setting;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Puts the mobile app's delivery arrangement where the admin can edit it:
 * /admin/setting. Existing rows are left alone, so re-running never overwrites
 * a figure somebody has since changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ShopDelivery::seedRows() as $row) {
            if (Setting::query()->where('slug', $row['slug'])->exists()) {
                continue;
            }

            SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', [ShopDelivery::COST, ShopDelivery::PRICE, ShopDelivery::THRESHOLD])->delete();
        SiteSettings::forget();
    }
};
