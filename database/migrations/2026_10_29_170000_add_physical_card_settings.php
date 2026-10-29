<?php

use App\Models\Setting;
use App\Support\PhysicalCard;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Adds the physical card price and its shipping price (50 / 50) to /admin/setting. Only adds rows; an existing value is kept. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PhysicalCard::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(PhysicalCard::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
