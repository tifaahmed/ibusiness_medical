<?php

use App\Models\Setting;
use App\Support\PaymentMethods;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PaymentMethods::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', array_column(PaymentMethods::seedRows(), 'slug'))->delete();
    }
};
