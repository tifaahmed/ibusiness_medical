<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Two things an admin does to members' balances as ONE step, so a half-done move can never exist:
 *
 *  - move points or wallet pounds from one member to another (a debit and a credit written together);
 *  - turn some of a member's points into pounds in their own wallet (the points out, the pounds in).
 *
 * Both only ever ADD ledger rows (the ledgers are append-only), each stamped with the admin and the reason, and the members'
 * rows are locked (lowest id first, so two admins moving money between the same two members cannot deadlock). The member
 * switches (`loyalty_transfers_enabled`, `loyalty_wallet_enabled`) and the member minimum do not apply: this is the admin's call.
 */
final class AdminLedgerMoves
{
    /**
     * @return array{ok: bool, reason: ?string, amount?: float|int}  reason: same_member | no_rate | not_enough
     */
    public static function transfer(User $from, User $to, string $kind, float|int $amount, int $adminId, string $note): array
    {
        if ($from->id === $to->id) {
            return ['ok' => false, 'reason' => 'same_member'];
        }

        $isPoints = $kind === WalletTransfer::KIND_POINTS;
        $amount = $isPoints ? (int) $amount : round((float) $amount, 2);

        return DB::transaction(function () use ($from, $to, $isPoints, $amount, $adminId, $note) {
            self::lock($from->id, $to->id);

            $has = $isPoints ? LoyaltyTransaction::balanceOf($from->id) : WalletTransaction::balanceOf($from->id);
            if ($has < $amount) {
                return ['ok' => false, 'reason' => 'not_enough', 'has' => $has];
            }

            $pair = (string) Str::uuid();
            $reason = Str::limit($note, 200, '');

            if ($isPoints) {
                LoyaltyTransaction::query()->create(['user_id' => $from->id, 'source' => LoyaltyTransaction::SOURCE_TRANSFER, 'client_id' => "admin-out:{$pair}", 'points' => -$amount, 'note' => "admin: to #{$to->id} — {$reason}", 'created_by' => $adminId, 'answered_at' => now()]);
                LoyaltyTransaction::query()->create(['user_id' => $to->id, 'source' => LoyaltyTransaction::SOURCE_TRANSFER, 'client_id' => "admin-in:{$pair}", 'points' => $amount, 'note' => "admin: from #{$from->id} — {$reason}", 'created_by' => $adminId, 'answered_at' => now()]);
            } else {
                WalletTransaction::query()->create(['user_id' => $from->id, 'source' => WalletTransaction::SOURCE_TRANSFER, 'client_id' => "admin-out:{$pair}", 'amount' => -$amount, 'note' => "admin: to #{$to->id} — {$reason}", 'created_by' => $adminId]);
                WalletTransaction::query()->create(['user_id' => $to->id, 'source' => WalletTransaction::SOURCE_TRANSFER, 'client_id' => "admin-in:{$pair}", 'amount' => $amount, 'note' => "admin: from #{$from->id} — {$reason}", 'created_by' => $adminId]);
            }

            return ['ok' => true, 'reason' => null, 'amount' => $amount];
        });
    }

    /**
     * `$egp` whole pounds of the member's points go into their wallet, at the admin's rate (`loyalty_points_per_egp`).
     *
     * @return array{ok: bool, reason: ?string, egp?: int, points?: int}  reason: no_rate | not_enough
     */
    public static function convertPoints(User $member, int $egp, int $adminId, string $note): array
    {
        if (LoyaltyPoints::pointsPerEgp() <= 0) {
            return ['ok' => false, 'reason' => 'no_rate'];
        }

        $points = LoyaltyWallet::pointsFor($egp);

        return DB::transaction(function () use ($member, $egp, $points, $adminId, $note) {
            self::lock($member->id);

            if (LoyaltyTransaction::balanceOf($member->id) < $points) {
                return ['ok' => false, 'reason' => 'not_enough'];
            }

            $id = 'admin-convert:'.Str::uuid();
            $reason = Str::limit($note, 200, '');

            LoyaltyTransaction::query()->create(['user_id' => $member->id, 'source' => LoyaltyTransaction::SOURCE_WALLET, 'client_id' => $id, 'points' => -$points, 'note' => "admin: wallet {$egp} EGP — {$reason}", 'created_by' => $adminId, 'answered_at' => now()]);
            WalletTransaction::query()->create(['user_id' => $member->id, 'source' => WalletTransaction::SOURCE_POINTS, 'client_id' => $id, 'amount' => $egp, 'points' => $points, 'points_per_egp' => LoyaltyPoints::pointsPerEgp(), 'note' => "admin — {$reason}", 'created_by' => $adminId]);

            return ['ok' => true, 'reason' => null, 'egp' => $egp, 'points' => $points];
        });
    }

    /** Lock the members' rows, lowest id first. */
    private static function lock(int ...$ids): void
    {
        sort($ids);
        User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }
}
