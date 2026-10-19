<?php

use App\Models\Setting;
use App\Support\ContactAlert;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (ContactAlert::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(ContactAlert::seedRows(), 'slug'))->delete();
    }
};
