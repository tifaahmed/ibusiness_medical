<?php

use App\Models\Setting;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The delivery figures used to be the mobile app's alone (`mobile_*`). They are
 * now the whole shop's — the website's orders and the app's are written with
 * the same ones — and the wallet number joins them. Rows already edited keep
 * their values; anything still missing is created with the install defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ShopDelivery::LEGACY_SLUGS as $new => $legacy) {
            $old = Setting::query()->where('slug', $legacy)->first();

            if ($old !== null && ! Setting::query()->where('slug', $new)->exists()) {
                $old->update(['slug' => $new]);
            }
        }

        SiteSettings::forget();

        foreach (ShopDelivery::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        foreach (ShopDelivery::LEGACY_SLUGS as $new => $legacy) {
            Setting::query()->where('slug', $new)->update(['slug' => $legacy]);
        }

        Setting::query()->where('slug', ShopDelivery::WALLET)->delete();
        SiteSettings::forget();
    }
};
