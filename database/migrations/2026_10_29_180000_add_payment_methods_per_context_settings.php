<?php

use App\Models\Setting;
use App\Support\PaymentMethods;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * One switch per payment method per place it is offered (products, physical cards, memberships) in /admin/setting.
 * All on, so nothing changes until the admin turns one off. Only adds rows; an existing value is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PaymentMethods::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        $slugs = [];

        foreach (PaymentMethods::CONTEXTS as $context) {
            foreach ([PaymentMethods::ONLINE_CARD, PaymentMethods::ONLINE_WALLET, PaymentMethods::COD, PaymentMethods::WALLET, PaymentMethods::INSTAPAY] as $main) {
                $slugs[] = PaymentMethods::contextSlug($context, $main);
            }
        }

        Setting::query()->whereIn('slug', $slugs)->delete();
        SiteSettings::forget();
    }
};
