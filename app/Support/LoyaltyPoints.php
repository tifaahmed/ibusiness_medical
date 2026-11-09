<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How many loyalty points a quiz answer is worth, and what 1 EGP is worth in points. The figures live in config/loyalty.php
 * (the tiers) and in /admin/setting (`loyalty_points_per_egp`); a client only says whether it was right and how long it took.
 *
 * Right answer: within 5 s = 100, within 10 s = 75, within 15 s = 50, within 20 s = 25. Wrong or out of time costs the admin setting
 * `loyalty_points_wrong` (a number of points to take away; 0 = a wrong answer costs nothing, which is the default).
 */
final class LoyaltyPoints
{
    /** The admin setting: how many points are worth ONE Egyptian pound. */
    public const POINTS_PER_EGP = 'loyalty_points_per_egp';

    public const DEFAULT_POINTS_PER_EGP = 100;

    /** The admin setting: how many points a wrong answer (or a missed clock) takes away. 0 = nothing. */
    public const POINTS_WRONG = 'loyalty_points_wrong';

    public const DEFAULT_POINTS_WRONG = 0;

    /** Most points ONE order may spend (0 = no limit) and most points ONE product line of an order may take (0 = no limit). */
    public const MAX_PER_ORDER = 'loyalty_points_max_per_order';

    public const MAX_PER_PRODUCT = 'loyalty_points_max_per_product';

    /** Points for an answer. `$seconds` is the time the player took (null = unknown, which pays the lowest tier). */
    public static function forAnswer(bool $correct, ?float $seconds): int
    {
        if (! $correct) {
            return self::pointsWrong();
        }

        $tiers = (array) config('loyalty.quiz.speed_tiers');
        ksort($tiers);

        if ($tiers === []) {
            return (int) config('loyalty.quiz.points_right');
        }

        if ($seconds === null || $seconds < 0) {
            return (int) end($tiers);
        }

        foreach ($tiers as $upTo => $points) {
            if ($seconds <= $upTo) {
                return (int) $points;
            }
        }

        return (int) end($tiers);
    }

    /**
     * What a wrong answer adds to the balance: zero or a negative number. The admin types the loss as a positive number
     * ("3 points"); a minus sign typed by mistake means the same.
     */
    public static function pointsWrong(): int
    {
        return -abs((int) round((float) SiteSettings::get(self::POINTS_WRONG, self::DEFAULT_POINTS_WRONG)));
    }

    /** How many points ONE pound is worth (never below 0). */
    public static function pointsPerEgp(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::POINTS_PER_EGP, self::DEFAULT_POINTS_PER_EGP), 4));
    }

    /** Most points one order may spend; null = no limit. */
    public static function maxPerOrder(): ?int
    {
        $max = (int) SiteSettings::get(self::MAX_PER_ORDER, 0);

        return $max > 0 ? $max : null;
    }

    /** The system-wide most points one product (all its units in one order) may take; null = no limit. A product's own `points_max` overrides it. */
    public static function maxPerProduct(): ?int
    {
        $max = (int) SiteSettings::get(self::MAX_PER_PRODUCT, 0);

        return $max > 0 ? $max : null;
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::POINTS_PER_EGP, 'name' => ['en' => 'Loyalty points worth 1 EGP (1 pound = how many points)', 'ar' => 'كم نقطة تساوي 1 جنيه (النقاط مقابل الجنيه)'], 'value' => (string) self::DEFAULT_POINTS_PER_EGP, 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MAX_PER_ORDER, 'name' => ['en' => 'Most loyalty points one order may spend (0 = no limit)', 'ar' => 'أقصى عدد نقاط ولاء يمكن استخدامه في الطلب الواحد (0 = بدون حد)'], 'value' => '0', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MAX_PER_PRODUCT, 'name' => ['en' => 'Most loyalty points one product may take in an order (0 = no limit; a product can set its own)', 'ar' => 'أقصى عدد نقاط ولاء يمكن استخدامه على المنتج الواحد (0 = بدون حد؛ ولكل منتج حد خاص)'], 'value' => '0', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::POINTS_WRONG, 'name' => ['en' => 'Loyalty points taken away for a wrong answer in the quiz (0 = nothing)', 'ar' => 'كم نقطة تُخصم عند الإجابة الخاطئة في اللعبة (0 = لا يُخصم شيء)'], 'value' => (string) self::DEFAULT_POINTS_WRONG, 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
