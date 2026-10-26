<?php

use App\Models\Setting;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([MembershipPlan::HALF_PRICE, MembershipPlan::HALF_MONTHS] as $slug) {
            $row = collect(MembershipPlan::seedRows())->firstWhere('slug', $slug);

            if (! Setting::query()->where('slug', $slug)->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        /* The yearly plan was never priced (0 = not sold online): 600 EGP, editable at /admin/setting. */
        if (MembershipPlan::price() <= 0) {
            SiteSettings::put(MembershipPlan::PRICE, '600', Setting::TYPE_NUMBER);
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', [MembershipPlan::HALF_PRICE, MembershipPlan::HALF_MONTHS])->delete();
    }
};
