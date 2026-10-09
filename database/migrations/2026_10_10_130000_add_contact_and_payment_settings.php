<?php

use App\Models\Setting;
use App\Support\ShopContact;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The contact points join the shop's settings at
 * /admin/setting. Existing rows are left alone — re-running never overwrites a
 * figure somebody has since changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([...ShopContact::seedRows(), ...ShopDelivery::seedRows()] as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(ShopContact::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
