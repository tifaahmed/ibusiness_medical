<?php

use App\Models\Setting;
use App\Support\MemberTransfers;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Adds the switch for member-to-member transfers (OFF) to /admin/setting. Only adds the row; an existing value is kept. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (MemberTransfers::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(MemberTransfers::seedRows(), 'slug'))->delete();
        SiteSettings::forget();
    }
};
