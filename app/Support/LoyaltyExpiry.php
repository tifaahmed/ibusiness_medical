<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points expire `loyalty_points_expiry_months` (default 6) after they were EARNED, and spending always eats the
 * OLDEST points first (FIFO), so what a member spent can never be the thing that expires.
 *
 * The ledger stays append-only. Nothing is stored twice: `lots()` replays a member's rows in the order they were written —
 * every positive row is a lot with its earn date, every negative row (a spend, a wrong answer, a transfer out, a previous
 * expiry) takes from the oldest lot that still has points. What is left of a lot older than the limit is written as ONE
 * negative `expired` row (`client_id = expire:<lot id>`, so a rerun books nothing twice).
 */
final class LoyaltyExpiry
{
    public const ENABLED = 'loyalty_points_expiry_enabled';

    public const MONTHS = 'loyalty_points_expiry_months';

    public const DEFAULT_MONTHS = 6;

    public static function enabled(): bool
    {
        return (bool) SiteSettings::get(self::ENABLED, false);
    }

    public static function months(): int
    {
        return max(1, (int) SiteSettings::get(self::MONTHS, self::DEFAULT_MONTHS));
    }

    /**
     * What is left of each earning, oldest first, after every spend took from the oldest.
     *
     * @return array<int, array{id: int, earned_at: Carbon, remaining: int}>
     */
    public static function lots(int $userId): array
    {
        $lots = [];

        LoyaltyTransaction::query()->where('user_id', $userId)->orderBy('id')->get(['id', 'points', 'answered_at', 'created_at'])
            ->each(function (LoyaltyTransaction $row) use (&$lots): void {
                if ($row->points > 0) {
                    $lots[] = ['id' => $row->id, 'earned_at' => $row->answered_at ?? $row->created_at, 'remaining' => $row->points];

                    return;
                }

                $debt = -$row->points;
                usort($lots, fn (array $a, array $b) => [$a['earned_at'], $a['id']] <=> [$b['earned_at'], $b['id']]);

                foreach ($lots as &$lot) {
                    if ($debt <= 0) {
                        break;
                    }
                    $take = min($lot['remaining'], $debt);
                    $lot['remaining'] -= $take;
                    $debt -= $take;
                }
                unset($lot);
            });

        usort($lots, fn (array $a, array $b) => [$a['earned_at'], $a['id']] <=> [$b['earned_at'], $b['id']]);

        return array_values(array_filter($lots, fn (array $lot) => $lot['remaining'] > 0));
    }

    /** How many days ahead a member is warned (the warning lasts the month before the points expire). */
    public const WARN_DAYS = 30;

    /**
     * Points that will expire within the next month, for the member's notice: the total and the date the FIRST of them goes.
     * Null when expiry is off or nothing is close. Spending takes the oldest first, so using points shrinks this figure.
     *
     * @return array{points: int, expires_on: string, days: int}|null
     */
    public static function expiringSoon(int $userId, ?Carbon $now = null): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $now = $now ?? now();
        $horizon = $now->copy()->addDays(self::WARN_DAYS);
        $points = 0;
        $first = null;

        foreach (self::lots($userId) as $lot) {
            $goes = $lot['earned_at']->copy()->addMonthsNoOverflow(self::months());
            if ($goes->greaterThan($horizon)) {
                break; // lots are oldest first
            }
            $points += $lot['remaining'];
            $first ??= $goes;
        }

        return $points > 0 && $first
            ? ['points' => $points, 'expires_on' => $first->toDateString(), 'days' => max(0, (int) ceil($now->floatDiffInDays($first, false)))]
            : null;
    }

    /** Books the expiry of one member's old points. Returns the number of points expired. */
    public static function expireFor(int $userId, ?Carbon $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subMonthsNoOverflow(self::months());

        return DB::transaction(function () use ($userId, $cutoff): int {
            // Lock the member's rows' owner so a spend in the same instant waits for us (the spend paths lock the member too).
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            $total = 0;
            foreach (self::lots($userId) as $lot) {
                if ($lot['earned_at']->greaterThan($cutoff)) {
                    continue;
                }
                $clientId = 'expire:'.$lot['id'];
                if (LoyaltyTransaction::query()->where('user_id', $userId)->where('client_id', $clientId)->exists()) {
                    continue;
                }
                LoyaltyTransaction::query()->create([
                    'user_id' => $userId, 'source' => LoyaltyTransaction::SOURCE_EXPIRED, 'client_id' => $clientId,
                    'points' => -$lot['remaining'], 'note' => 'expired: earned '.$lot['earned_at']->toDateString().' (row #'.$lot['id'].')', 'answered_at' => now(),
                ]);
                $total += $lot['remaining'];
            }

            return $total;
        });
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::ENABLED, 'name' => ['en' => 'Expire loyalty points after a number of months (run on the 1st of each month)', 'ar' => 'انتهاء صلاحية نقاط الولاء بعد عدد شهور (يشتغل أول كل شهر)'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
            ['slug' => self::MONTHS, 'name' => ['en' => 'Months before loyalty points expire', 'ar' => 'عدد الشهور قبل انتهاء نقاط الولاء'], 'value' => (string) self::DEFAULT_MONTHS, 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
