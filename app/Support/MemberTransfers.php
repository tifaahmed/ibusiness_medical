<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Services\Otp\MemberOtp;
use Illuminate\Support\Facades\DB;

/**
 * Members sending loyalty points or wallet money to each other. BOTH sides agree: the sender asks, the recipient accepts (or
 * declines), the sender may cancel while it waits, and it expires. Nothing moves before the acceptance; at that moment, in ONE
 * database transaction with both members locked in a fixed order, the sender must still have enough, and two rows are written
 * (a debit and a credit that share `transfer-out:<id>` / `transfer-in:<id>`), so a repeated tap books once.
 *
 *   loyalty_transfers_enabled   the admin switch (default OFF)
 *
 * The recipient is found by phone or by card number; a client never picks a user id.
 */
final class MemberTransfers
{
    public const ENABLED = 'loyalty_transfers_enabled';

    /** How long a request waits for an answer. */
    public const EXPIRES_HOURS = 48;

    /** The most open requests one sender may have. */
    public const MAX_OPEN = 5;

    public static function enabled(): bool
    {
        return (bool) SiteSettings::get(self::ENABLED, false);
    }

    /** The member behind a phone number or a card number, or null. A member never finds themself. */
    public static function find(?string $phone, ?string $card, int $exceptUserId): ?User
    {
        $user = null;

        if (filled($card)) {
            $user = Membership::query()->where('membership_number', trim((string) $card))->first()?->user;
        } elseif (filled($phone)) {
            $user = app(MemberOtp::class)->findMember((string) $phone);
        }

        return $user !== null && $user->id !== $exceptUserId ? $user : null;
    }

    /** A name that lets the sender check it is the right person without exposing them: first name and the last two digits of the phone. */
    public static function masked(User $user): string
    {
        $first = explode(' ', trim((string) $user->name))[0] ?: '—';
        $phone = preg_replace('/\D/', '', (string) $user->phone);

        return $first.($phone !== '' ? ' ••'.substr($phone, -2) : '');
    }

    /** What the sender has of this kind: points (whole) or wallet pounds. */
    public static function balance(int $userId, string $kind): float
    {
        return $kind === WalletTransfer::KIND_POINTS ? (float) LoyaltyTransaction::balanceOf($userId) : WalletTransaction::balanceOf($userId);
    }

    /**
     * @return array{ok: bool, reason: ?string, transfer?: WalletTransfer, duplicate?: bool}
     *         reason: disabled | not_found | not_enough | too_many_open | bad_amount
     */
    public static function request(User $from, ?string $phone, ?string $card, string $kind, float $amount, string $clientId, ?string $note): array
    {
        if (! self::enabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }

        $to = self::find($phone, $card, $from->id);
        if ($to === null) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $amount = $kind === WalletTransfer::KIND_POINTS ? floor($amount) : round($amount, 2);
        if ($amount < ($kind === WalletTransfer::KIND_POINTS ? 1 : 1)) {
            return ['ok' => false, 'reason' => 'bad_amount'];
        }

        $existing = WalletTransfer::query()->where('from_user_id', $from->id)->where('client_id', $clientId)->first();
        if ($existing !== null) {
            return ['ok' => true, 'reason' => null, 'transfer' => $existing, 'duplicate' => true];
        }

        if (self::balance($from->id, $kind) < $amount) {
            return ['ok' => false, 'reason' => 'not_enough'];
        }

        $open = WalletTransfer::query()->where('from_user_id', $from->id)->where('status', WalletTransfer::PENDING)->where('expires_at', '>', now())->count();
        if ($open >= self::MAX_OPEN) {
            return ['ok' => false, 'reason' => 'too_many_open'];
        }

        $transfer = WalletTransfer::query()->create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'kind' => $kind, 'amount' => $amount, 'status' => WalletTransfer::PENDING,
            'client_id' => $clientId, 'note' => $note, 'expires_at' => now()->addHours(self::EXPIRES_HOURS),
        ]);

        return ['ok' => true, 'reason' => null, 'transfer' => $transfer, 'duplicate' => false];
    }

    /**
     * The recipient says yes: the money moves now.
     *
     * @return array{ok: bool, reason: ?string, transfer?: WalletTransfer}  reason: not_found | not_open | not_enough
     */
    public static function accept(User $recipient, int $transferId): array
    {
        return DB::transaction(function () use ($recipient, $transferId) {
            $transfer = WalletTransfer::query()->where('id', $transferId)->where('to_user_id', $recipient->id)->lockForUpdate()->first();
            if ($transfer === null) {
                return ['ok' => false, 'reason' => 'not_found'];
            }

            if ($transfer->status !== WalletTransfer::PENDING) {
                return ['ok' => false, 'reason' => 'not_open', 'transfer' => $transfer];
            }

            if (! $transfer->expires_at->isFuture()) {
                $transfer->forceFill(['status' => WalletTransfer::EXPIRED, 'responded_at' => now()])->save();

                return ['ok' => false, 'reason' => 'not_open', 'transfer' => $transfer];
            }

            // Both members are locked in id order, so two transfers crossing each other cannot deadlock.
            User::query()->whereIn('id', [$transfer->from_user_id, $transfer->to_user_id])->orderBy('id')->lockForUpdate()->get();

            $amount = (float) $transfer->amount;
            if (self::balance($transfer->from_user_id, $transfer->kind) < $amount) {
                return ['ok' => false, 'reason' => 'not_enough', 'transfer' => $transfer];
            }

            if ($transfer->kind === WalletTransfer::KIND_POINTS) {
                $points = (int) $amount;
                LoyaltyTransaction::query()->create(['user_id' => $transfer->from_user_id, 'source' => LoyaltyTransaction::SOURCE_TRANSFER, 'client_id' => 'transfer-out:'.$transfer->id, 'points' => -$points, 'note' => 'transfer to #'.$transfer->to_user_id]);
                LoyaltyTransaction::query()->create(['user_id' => $transfer->to_user_id, 'source' => LoyaltyTransaction::SOURCE_TRANSFER, 'client_id' => 'transfer-in:'.$transfer->id, 'points' => $points, 'note' => 'transfer from #'.$transfer->from_user_id]);
            } else {
                WalletTransaction::query()->create(['user_id' => $transfer->from_user_id, 'source' => WalletTransaction::SOURCE_TRANSFER, 'client_id' => 'transfer-out:'.$transfer->id, 'amount' => -$amount, 'note' => 'transfer to #'.$transfer->to_user_id]);
                WalletTransaction::query()->create(['user_id' => $transfer->to_user_id, 'source' => WalletTransaction::SOURCE_TRANSFER, 'client_id' => 'transfer-in:'.$transfer->id, 'amount' => $amount, 'note' => 'transfer from #'.$transfer->from_user_id]);
            }

            $transfer->forceFill(['status' => WalletTransfer::ACCEPTED, 'responded_at' => now()])->save();

            return ['ok' => true, 'reason' => null, 'transfer' => $transfer];
        });
    }

    /** Closes a waiting request without moving anything: the recipient declines, or the sender cancels. */
    public static function close(User $actor, int $transferId, string $as): bool
    {
        $column = $as === WalletTransfer::DECLINED ? 'to_user_id' : 'from_user_id';
        $transfer = WalletTransfer::query()->where('id', $transferId)->where($column, $actor->id)->where('status', WalletTransfer::PENDING)->first();

        if ($transfer === null) {
            return false;
        }

        $transfer->forceFill(['status' => $as, 'responded_at' => now()])->save();

        return true;
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::ENABLED, 'name' => ['en' => 'Let members send points / wallet money to each other (with the recipient accepting)', 'ar' => 'السماح للأعضاء بتحويل النقاط / رصيد المحفظة لبعضهم (بموافقة المستلم)'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
        ];
    }
}
