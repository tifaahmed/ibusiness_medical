<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sharing the referral code gives the member's quiz plays back: +1 play on EVERY section for that day, per refill.
 * A share cannot be verified (the phone's share sheet says nothing about whether it was sent), so the number of
 * refills a day is capped by the admin (`quiz_share_refills_per_day`, default 1, 0 = off) — that cap is what stops
 * farming. Points for the extra plays are booked normally by the quiz upload, whose daily cap is
 * `plays_per_day + refills of that day`.
 */
final class QuizShareRefills
{
    public const SETTING = 'quiz_share_refills_per_day';

    public static function perDay(): int
    {
        return max(0, (int) SiteSettings::get(self::SETTING, 1));
    }

    private static function day(?Carbon $at = null): string
    {
        return ($at ?? now())->timezone((string) config('app.timezone'))->toDateString();
    }

    public const REFERRAL_SETTING = 'quiz_referral_refills_per_day';

    private static function count(int $userId, string $reason): int
    {
        return (int) DB::table('quiz_share_refills')->where('user_id', $userId)->where('day', self::day())->where('reason', $reason)->count();
    }

    /** Share refills still available today (what the "share to unlock" button reads). */
    public static function sharesLeftToday(int $userId): int
    {
        return max(0, self::perDay() - self::count($userId, 'share'));
    }

    /**
     * Someone registered under this member's code — downloaded the app and joined; payment is NOT required.
     * One refill per referral, ever (unique `referral_id`), at most `quiz_referral_refills_per_day` a day
     * (a guard against throw-away accounts; 0 = off). Never throws: a failed refill must not undo a referral.
     */
    public static function forReferral(\App\Models\Referral $referral): void
    {
        try {
            $limit = max(0, (int) SiteSettings::get(self::REFERRAL_SETTING, 5));
            $userId = (int) $referral->referrer_user_id;

            if ($limit === 0 || $userId === 0 || self::count($userId, 'referral') >= $limit) {
                return;
            }

            DB::table('quiz_share_refills')->insertOrIgnore([
                'user_id' => $userId, 'day' => self::day(), 'reason' => 'referral', 'referral_id' => $referral->id, 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Quiz refill for a referral failed.', ['referral_id' => $referral->id, 'message' => $e->getMessage()]);
        }
    }

    /** Refills the member already earned on `$at`'s day (= extra plays on every section that day). */
    public static function bonusFor(int $userId, ?Carbon $at = null): int
    {
        return (int) DB::table('quiz_share_refills')->where('user_id', $userId)->where('day', self::day($at))->count();
    }

    /**
     * Record one share. Returns the new state; `granted` is false once today's cap is used.
     *
     * @return array{granted: bool, bonus_plays_today: int, refills_left_today: int}
     */
    public static function share(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            // Lock the member's row so two taps at once cannot both take the last refill.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $limit = self::perDay();
            // The share cap counts SHARE refills only: refills earned by a registration do not use it up.
            $shared = self::count($userId, 'share');
            $granted = $shared < $limit;

            if ($granted) {
                DB::table('quiz_share_refills')->insert(['user_id' => $userId, 'day' => self::day(), 'reason' => 'share', 'created_at' => now()]);
                $shared++;
            }

            return ['granted' => $granted, 'bonus_plays_today' => self::bonusFor($userId), 'refills_left_today' => max(0, $limit - $shared)];
        });
    }
}
