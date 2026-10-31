<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Turning loyalty points into money in the member's wallet. The rate is the admin setting `loyalty_points_per_egp`
 * (LoyaltyPoints::pointsPerEgp()); this class does the sums and the booking, a client only asks for a whole number of pounds.
 *
 *   loyalty_wallet_enabled   the switch (default OFF): while off nobody can convert, but points still show their value in pounds
 *   loyalty_wallet_min_egp   the smallest conversion in pounds (default 20)
 *
 * A conversion is ONE database transaction: a negative `loyalty_transactions` row (source `wallet`, the points spent) and a positive
 * `wallet_transactions` row (the pounds), sharing the phone's `client_id`, so a repeated request books once and the member's row is locked
 * so two requests cannot spend the same points.
 */
final class LoyaltyWallet
{
    public const ENABLED = 'loyalty_wallet_enabled';

    public const MIN_EGP = 'loyalty_wallet_min_egp';

    public static function enabled(): bool
    {
        return (bool) SiteSettings::get(self::ENABLED, false);
    }

    public static function minEgp(): int
    {
        return max(1, (int) SiteSettings::get(self::MIN_EGP, 20));
    }

    /** Whole pounds that `$points` are worth (0 when the rate is not set). */
    public static function wholeEgp(int $points): int
    {
        $rate = LoyaltyPoints::pointsPerEgp();

        return $rate > 0 && $points > 0 ? (int) floor($points / $rate) : 0;
    }

    /** What `$points` are worth in pounds, with pennies, for showing. */
    public static function egpValue(int $points): float
    {
        $rate = LoyaltyPoints::pointsPerEgp();

        return $rate > 0 && $points > 0 ? round($points / $rate, 2) : 0.0;
    }

    /** The points `$egp` whole pounds cost (rounded up so the member never gets more than the rate allows). */
    public static function pointsFor(int $egp): int
    {
        return (int) ceil($egp * LoyaltyPoints::pointsPerEgp());
    }

    /**
     * @return array{ok: bool, reason: ?string, duplicate?: bool, egp?: int, points?: int}
     *         reason: disabled | no_rate | below_minimum | not_enough_points
     */
    public static function convert(User $member, int $egp, string $clientId): array
    {
        if (! self::enabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }

        if (LoyaltyPoints::pointsPerEgp() <= 0) {
            return ['ok' => false, 'reason' => 'no_rate'];
        }

        if ($egp < self::minEgp()) {
            return ['ok' => false, 'reason' => 'below_minimum'];
        }

        $points = self::pointsFor($egp);

        try {
            return DB::transaction(function () use ($member, $egp, $points, $clientId) {
                // The member's row is the lock: two requests for the same member run one after the other.
                User::query()->whereKey($member->id)->lockForUpdate()->first();

                if (WalletTransaction::query()->where('user_id', $member->id)->where('client_id', $clientId)->exists()) {
                    return ['ok' => true, 'reason' => null, 'duplicate' => true, 'egp' => $egp, 'points' => $points];
                }

                if (LoyaltyTransaction::balanceOf($member->id) < $points) {
                    return ['ok' => false, 'reason' => 'not_enough_points'];
                }

                LoyaltyTransaction::query()->create([
                    'user_id' => $member->id, 'source' => LoyaltyTransaction::SOURCE_WALLET, 'client_id' => $clientId,
                    'points' => -$points, 'note' => "wallet: {$egp} EGP",
                ]);
                WalletTransaction::query()->create([
                    'user_id' => $member->id, 'source' => WalletTransaction::SOURCE_POINTS, 'client_id' => $clientId,
                    'amount' => $egp, 'points' => $points, 'points_per_egp' => LoyaltyPoints::pointsPerEgp(),
                ]);

                return ['ok' => true, 'reason' => null, 'duplicate' => false, 'egp' => $egp, 'points' => $points];
            });
        } catch (QueryException $e) {
            // The unique (user_id, client_id) index caught a race the check above could not: the same conversion was booked a moment ago.
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                return ['ok' => true, 'reason' => null, 'duplicate' => true, 'egp' => $egp, 'points' => $points];
            }

            throw $e;
        }
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::ENABLED, 'name' => ['en' => 'Let members convert loyalty points into their wallet', 'ar' => 'السماح للأعضاء بتحويل نقاط الولاء إلى المحفظة'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
            ['slug' => self::MIN_EGP, 'name' => ['en' => 'Smallest points-to-wallet conversion (EGP)', 'ar' => 'أقل مبلغ يمكن تحويله من النقاط إلى المحفظة (ج.م)'], 'value' => '20', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
