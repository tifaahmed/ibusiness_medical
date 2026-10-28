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

    /** How many DAYS a free trial card lasts (it used to be months: `membership_trial_months`, now removed). */
    public const TRIAL_DAYS = 'membership_trial_days';

    /** The shorter plan: its own price and months (0 price = not sold). */
    public const HALF_PRICE = 'membership_half_price';

    public const HALF_MONTHS = 'membership_half_months';

    public const PLAN_YEAR = 'year';

    public const PLAN_HALF = 'half';

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

    /**
     * Days a NEW free trial card lasts. Read once, when the card is made, and written into its `expiration_date`:
     * changing the setting never shortens or extends a card that already exists.
     */
    public static function trialDays(): int
    {
        return max(1, (int) SiteSettings::get(self::TRIAL_DAYS, 30));
    }

    public static function halfPrice(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::HALF_PRICE, 0), 2));
    }

    public static function halfMonths(): int
    {
        return max(1, (int) SiteSettings::get(self::HALF_MONTHS, 6));
    }

    /**
     * Every plan on sale (price above 0), the longer one first. Clients draw exactly these and send back `key`.
     *
     * @return list<array{key: string, price: float, months: int}>
     */
    public static function plans(): array
    {
        return array_values(array_filter([
            self::price() > 0 ? ['key' => self::PLAN_YEAR, 'price' => self::price(), 'months' => self::months()] : null,
            self::halfPrice() > 0 ? ['key' => self::PLAN_HALF, 'price' => self::halfPrice(), 'months' => self::halfMonths()] : null,
        ]));
    }

    /**
     * The plan a client asked for, or null when it is not on sale. No key means the longer plan
     * (what older app builds always bought).
     *
     * @return array{key: string, price: float, months: int}|null
     */
    public static function find(?string $key): ?array
    {
        $plans = self::plans();
        $key = $key ?: self::PLAN_YEAR;

        foreach ($plans as $plan) {
            if ($plan['key'] === $key) {
                return $plan;
            }
        }

        return null;
    }

    /** Whether "buy with Paymob" can be offered at all. */
    public static function canPayOnline(): bool
    {
        return self::plans() !== [] && PaymentMethods::online();
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::PRICE, 'name' => ['en' => 'Membership price (EGP, 0 = not sold online)', 'ar' => 'سعر الاشتراك (ج.م، 0 = لا يُباع أونلاين)'], 'value' => '0', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MONTHS, 'name' => ['en' => 'Months a paid subscription lasts', 'ar' => 'مدة الاشتراك المدفوع بالشهور'], 'value' => '12', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::HALF_PRICE, 'name' => ['en' => 'Half-year plan price (EGP, 0 = not sold)', 'ar' => 'سعر الاشتراك نصف السنوي (ج.م، 0 = غير متاح)'], 'value' => '350', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::HALF_MONTHS, 'name' => ['en' => 'Months the half-year plan lasts', 'ar' => 'مدة الاشتراك نصف السنوي بالشهور'], 'value' => '6', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::TRIAL_DAYS, 'name' => ['en' => 'Days a free trial card lasts (new cards only)', 'ar' => 'مدة الكارت التجريبي بالأيام (للكروت الجديدة فقط)'], 'value' => '30', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
