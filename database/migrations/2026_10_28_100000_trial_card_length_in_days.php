<?php

use App\Models\Setting;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The free trial is now set in DAYS (`membership_trial_days`) instead of months. The days start at what the months
 * were (30 per month), so nothing changes until an admin edits it. Cards already issued keep their own expiration date.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = Setting::query()->where('slug', 'membership_trial_months')->first();

        if (! Setting::query()->where('slug', MembershipPlan::TRIAL_DAYS)->exists()) {
            $row = collect(MembershipPlan::seedRows())->firstWhere('slug', MembershipPlan::TRIAL_DAYS);
            $days = $legacy ? max(1, (int) $legacy->value) * 30 : (int) $row['value'];

            SiteSettings::put($row['slug'], (string) $days, $row['value_type'], $row['name']);
        }

        $legacy?->delete();
        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->where('slug', MembershipPlan::TRIAL_DAYS)->delete();
    }
};
