<?php

namespace App\Support;

use App\Models\Setting;

/**
 * What a membership costs and how long a trial lasts. Edited at /admin/setting.
 *
 * A price of 0 (or blank) means "not for sale online": clients hide the
 * pay-with-Paymob button and show only the customer-services options, because
 * an online charge of 0 would be a free card for anybody who pressed it.
 */
final class MembershipPlan
{
    public const PRICE = 'membership_price';

    public const MONTHS = 'membership_months';

    public const TRIAL_MONTHS = 'membership_trial_months';

    /** The most first-degree relatives one card can carry (the family-member rule). */
    public const MAX_RELATIVES = 15;

    public static function price(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::PRICE, 0), 2));
    }

    /** How many months a paid subscription adds. */
    public static function months(): int
    {
        return max(1, (int) SiteSettings::get(self::MONTHS, 12));
    }

    public static function trialMonths(): int
    {
        return max(1, (int) SiteSettings::get(self::TRIAL_MONTHS, 1));
    }

    /** Whether "buy with Paymob" can be offered at all. */
    public static function canPayOnline(): bool
    {
        return self::price() > 0 && PaymentMethods::online();
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::PRICE, 'name' => ['en' => 'Membership price (EGP, 0 = not sold online)', 'ar' => 'سعر الاشتراك (ج.م، 0 = لا يُباع أونلاين)'], 'value' => '0', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MONTHS, 'name' => ['en' => 'Months a paid subscription lasts', 'ar' => 'مدة الاشتراك المدفوع بالشهور'], 'value' => '12', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::TRIAL_MONTHS, 'name' => ['en' => 'Months a free trial card lasts', 'ar' => 'مدة الكارت التجريبي بالشهور'], 'value' => '1', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
