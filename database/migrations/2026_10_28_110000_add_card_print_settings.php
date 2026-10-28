<?php

use App\Models\Setting;
use App\Support\CardPrint;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Adds the printed card size and back reduction to /admin/setting. Only adds rows; an existing value is kept. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (CardPrint::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(CardPrint::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
