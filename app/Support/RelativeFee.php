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

    public const PLAN_MONTH = 'month';

    public const PLAN_YEAR = 'year';

    /**
     * The two ways to pay for a relative (owner decision 2026-11-17): one month at `monthly()` (5) or the whole period at
     * `perRelative()` (60 for 12 months). A price of 0 = that plan is not sold.
     *
     * @return list<array{key: string, price: float, months: int}>
     */
    public static function plans(): array
    {
        return array_values(array_filter([
            self::monthly() > 0 ? ['key' => self::PLAN_MONTH, 'price' => self::monthly(), 'months' => 1] : null,
            self::perRelative() > 0 ? ['key' => self::PLAN_YEAR, 'price' => self::perRelative(), 'months' => self::months()] : null,
        ]));
    }

    /** @return array{key: string, price: float, months: int}|null */
    public static function plan(?string $key): ?array
    {
        foreach (self::plans() as $plan) {
            if ($plan['key'] === ($key ?: self::PLAN_YEAR)) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array{monthly: float, months: int, per_relative: float, plans: list<array{key: string, price: float, months: int}>, kind: string, pay_online: bool, request_only: bool} */
    public static function fee(?Membership $primary): array
    {
        $kind = $primary?->kind() ?? Membership::KIND_INDIVIDUAL;

        return [
            'monthly' => self::monthly(),
            'months' => self::months(),
            'per_relative' => self::perRelative(),
            'plans' => self::plans(),
            'kind' => $kind,
            // Everybody may pay online; a company is ALSO offered the monthly request.
            'pay_online' => self::plans() !== [],
            'request_only' => $kind === Membership::KIND_COMPANY,
        ];
    }

    /** Order-line slug that names the relative and the plan: `relative-fee-<id>-<plan>`. */
    public static function lineSlug(int $relativeId, string $plan): string
    {
        return 'relative-fee-'.$relativeId.'-'.$plan;
    }

    /**
     * Switches on / extends the relatives a paid order covers, ONCE (`orders.relative_fee_applied_at` is the lock). A renewal stacks on
     * the time still left; an expired or never-paid relative counts from today.
     */
    public static function activateFor(\App\Models\Order $order): int
    {
        if ($order->source !== self::SOURCE || $order->relative_fee_applied_at !== null) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order): int {
            $locked = \App\Models\Order::query()->lockForUpdate()->find($order->id);
            if ($locked === null || $locked->relative_fee_applied_at !== null) {
                return 0;
            }

            $count = 0;

            foreach ($locked->products()->get() as $line) {
                if (! preg_match('/^relative-fee-(\d+)(?:-(month|year))?$/', (string) $line->slug, $m)) {
                    continue;
                }

                $relative = Membership::query()->lockForUpdate()->find((int) $m[1]);
                $plan = self::plan($m[2] ?? self::PLAN_YEAR);
                if ($relative === null || ! $relative->isRelative() || $plan === null) {
                    continue;
                }

                $from = $relative->expiration_date?->isFuture() ? $relative->expiration_date->copy() : now();

                $relative->fee_order_id = $locked->id;
                $relative->forceFill([
                    'is_active' => true,
                    'approval_status' => \App\Enums\FamilyMember\ApprovalStatusEnum::APPROVED->value,
                    'approved_at' => $relative->approved_at ?? now(),
                    'expiration_date' => $from->addMonthsNoOverflow($plan['months'])->toDateString(),
                ])->save();
                $count++;
            }

            $locked->forceFill(['relative_fee_applied_at' => now()])->save();

            return $count;
        });
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
