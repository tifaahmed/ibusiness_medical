<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;

/**
 * Read-only loyalty and wallet picture of one member for the admin's member page: both balances, the quiz plays, every
 * points-to-wallet conversion, transfers to and from other members, and the same figures for each relative on the card.
 * Changes by hand stay in /admin/loyalty (they are logged with the admin there).
 */
final class AdminMemberLoyalty
{
    /** @return array<string, mixed> */
    public static function summary(User $user): array
    {
        $balance = LoyaltyTransaction::balanceOf($user->id);

        return [
            'points_per_egp' => LoyaltyPoints::pointsPerEgp(),
            'balance' => $balance,
            'egp_value' => LoyaltyWallet::egpValue(max(0, $balance)),
            'wallet_balance' => WalletTransaction::balanceOf($user->id),
            'min_convert_egp' => LoyaltyWallet::minEgp(),
            'plays' => self::plays($user->id),
            'conversions' => self::conversions($user->id),
            'transfers' => self::transfers($user->id),
            'family' => self::family($user),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function plays(int $userId): array
    {
        $rows = LoyaltyTransaction::query()->where('user_id', $userId)->where('source', LoyaltyTransaction::SOURCE_QUIZ)
            ->orderByDesc('answered_at')->limit(3000)->get(['client_id', 'play_id', 'quiz_key', 'correct', 'points', 'answered_at']);

        return $rows->groupBy(fn ($row) => $row->play_id ?: $row->client_id)->map(fn ($play, $id) => [
            'play_id' => (string) $id,
            'at' => $play->min('answered_at')?->format('Y-m-d H:i'),
            'quiz' => $play->first()->quiz_key,
            'right' => $play->where('correct', true)->count(),
            'wrong' => $play->where('correct', false)->count(),
            'points' => (int) $play->sum('points'),
        ])->sortByDesc('at')->take(50)->values()->all();
    }

    /** Each conversion is two ledger lines sharing a client id: points out, pounds in. */
    private static function conversions(int $userId): array
    {
        $spent = LoyaltyTransaction::query()->where('user_id', $userId)->where('source', LoyaltyTransaction::SOURCE_WALLET)->latest('id')->limit(100)->get();
        $pounds = WalletTransaction::query()->where('user_id', $userId)->where('source', WalletTransaction::SOURCE_POINTS)
            ->whereIn('client_id', $spent->pluck('client_id'))->get()->keyBy('client_id');

        return $spent->map(fn (LoyaltyTransaction $row) => [
            'at' => $row->created_at?->format('Y-m-d H:i'),
            'points' => abs((int) $row->points),
            'egp' => isset($pounds[$row->client_id]) ? (float) $pounds[$row->client_id]->amount : null,
            'note' => $row->note,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function transfers(int $userId): array
    {
        return WalletTransfer::query()->where(fn ($q) => $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId))
            ->with(['from:id,name,phone', 'to:id,name,phone'])->latest('id')->limit(50)->get()
            ->map(function (WalletTransfer $t) use ($userId) {
                $outgoing = $t->from_user_id === $userId;
                $other = $outgoing ? $t->to : $t->from;

                return [
                    'at' => $t->created_at?->format('Y-m-d H:i'),
                    'direction' => $outgoing ? 'out' : 'in',
                    'kind' => $t->kind,
                    'amount' => (float) $t->amount,
                    'status' => is_object($t->status) ? $t->status->value : (string) $t->status,
                    'other_name' => $other?->name,
                    'other_phone' => $other?->phone,
                ];
            })->all();
    }

    /** Relatives are members with their own user row: their own points and wallet. */
    private static function family(User $user): array
    {
        $relatives = Membership::query()->whereIn('parent_membership_id', $user->memberships()->select('id'))->with('user:id,name,phone')->get();

        return $relatives->map(fn (Membership $m) => [
            'user_id' => $m->user_id,
            'name' => $m->user?->name,
            'phone' => $m->user?->phone,
            'relationship' => $m->relationship,
            'membership_number' => $m->membership_number,
            'balance' => $m->user_id ? LoyaltyTransaction::balanceOf($m->user_id) : 0,
            'wallet_balance' => $m->user_id ? WalletTransaction::balanceOf($m->user_id) : 0.0,
            'plays' => $m->user_id ? LoyaltyTransaction::query()->where('user_id', $m->user_id)->where('source', LoyaltyTransaction::SOURCE_QUIZ)->distinct()->count('play_id') : 0,
        ])->all();
    }
}
