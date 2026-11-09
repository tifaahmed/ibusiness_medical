<?php

use App\Models\Setting;
use App\Support\Referrals;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Referral discount and commission become per plan (month / half / year). Adds settings only; the old single ones stay as the fallback. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Referrals::planSeedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(Referrals::planSeedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
