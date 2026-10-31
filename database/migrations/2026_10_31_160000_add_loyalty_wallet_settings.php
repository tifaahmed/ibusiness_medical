<?php

use App\Models\Setting;
use App\Support\LoyaltyWallet;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Adds the wallet switch (OFF) and the smallest conversion (20 EGP) to /admin/setting. Only adds rows; an existing value is kept. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (LoyaltyWallet::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(LoyaltyWallet::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
