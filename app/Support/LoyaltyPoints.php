<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How many loyalty points a quiz answer is worth, and what 1 EGP is worth in points. The figures live in config/loyalty.php
 * (the tiers) and in /admin/setting (`loyalty_points_per_egp`); a client only says whether it was right and how long it took.
 *
 * Right answer: within 5 s = 100, within 10 s = 75, within 15 s = 50, within 20 s = 25. Wrong or out of time = `points_wrong`.
 */
final class LoyaltyPoints
{
    /** The admin setting: how many points are worth ONE Egyptian pound. */
    public const POINTS_PER_EGP = 'loyalty_points_per_egp';

    public const DEFAULT_POINTS_PER_EGP = 100;

    /** Points for an answer. `$seconds` is the time the player took (null = unknown, which pays the lowest tier). */
    public static function forAnswer(bool $correct, ?float $seconds): int
    {
        if (! $correct) {
            return (int) config('loyalty.quiz.points_wrong');
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

    /** How many points ONE pound is worth (never below 0). */
    public static function pointsPerEgp(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::POINTS_PER_EGP, self::DEFAULT_POINTS_PER_EGP), 4));
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::POINTS_PER_EGP, 'name' => ['en' => 'Loyalty points worth 1 EGP (1 pound = how many points)', 'ar' => 'كم نقطة تساوي 1 جنيه (النقاط مقابل الجنيه)'], 'value' => (string) self::DEFAULT_POINTS_PER_EGP, 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
