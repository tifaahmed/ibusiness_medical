<?php

use App\Models\Setting;
use App\Support\LoyaltyPoints;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Adds the loyalty settings that are not there yet (today: points taken away for a wrong quiz answer, default 0). Only adds rows; an existing value is kept. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (LoyaltyPoints::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(LoyaltyPoints::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
