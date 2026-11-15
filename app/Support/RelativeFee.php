<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\Setting;

/**
 * What a RELATIVE costs on a card (owner decision 2026-11-11). Edited at /admin/setting:
 *
 *   relative_monthly_price  EGP per relative per month (default 5)
 *   relative_months         how many months one payment activates the relative for (default 12 = a year)
 *
 * An INDIVIDUAL card pays the whole period at once (5 × 12 = 60) and the relative is active at once with an expiry date
 * a year ahead. A COMPANY card pays nothing in the app: the app shows the cost and files a request in the contact inbox
 * (monthly, salary deduction or instalments are agreed by phone) — or the member may pay the year at once like an individual.
 * Clients draw `fee()`; they never compute the price.
 */
final class RelativeFee
{
    public const MONTHLY = 'relative_monthly_price';

    public const MONTHS = 'relative_months';

    /** `orders.source` of an order that pays relatives' fees. */
    public const SOURCE = 'relative_fee';

    public static function monthly(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::MONTHLY, 5), 2));
    }

    public static function months(): int
    {
        return max(1, (int) SiteSettings::get(self::MONTHS, 12));
    }

    /** One relative, the whole period, paid at once. */
    public static function perRelative(): float
    {
        return round(self::monthly() * self::months(), 2);
    }

    /** @return array{monthly: float, months: int, per_relative: float, kind: string, pay_online: bool, request_only: bool} */
    public static function fee(?Membership $primary): array
    {
        $kind = $primary?->kind() ?? Membership::KIND_INDIVIDUAL;

        return [
            'monthly' => self::monthly(),
            'months' => self::months(),
            'per_relative' => self::perRelative(),
            'kind' => $kind,
            // Everybody may pay the period at once; a company is ALSO offered the monthly request.
            'pay_online' => self::perRelative() > 0,
            'request_only' => $kind === Membership::KIND_COMPANY,
        ];
    }

    /** Switches the relatives a paid order covers on, for the period, once. */
    public static function activateFor(\App\Models\Order $order): int
    {
        if ($order->source !== self::SOURCE) {
            return 0;
        }

        $count = 0;

        Membership::query()->where('fee_order_id', $order->id)->where('is_active', false)->get()->each(function (Membership $relative) use (&$count): void {
            $relative->forceFill([
                'is_active' => true,
                'approval_status' => \App\Enums\FamilyMember\ApprovalStatusEnum::APPROVED->value,
                'approved_at' => now(),
                'expiration_date' => now()->addMonths(self::months())->toDateString(),
            ])->save();
            $count++;
        });

        return $count;
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::MONTHLY, 'name' => ['en' => 'Relative fee (EGP per relative per month)', 'ar' => 'رسوم القريب (ج.م للقريب في الشهر)'], 'value' => '5', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MONTHS, 'name' => ['en' => 'Months one relative payment covers', 'ar' => 'عدد شهور تفعيل القريب بالدفعة الواحدة'], 'value' => '12', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
