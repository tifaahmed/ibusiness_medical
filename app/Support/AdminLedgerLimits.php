<?php

namespace App\Support;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\AdminLedgerLimit;
use App\Models\LoyaltyTransaction;
use App\Models\RoleLedgerLimit;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Who may write to a member's loyalty points / wallet by hand, and how much per month.
 *
 * Four permissions (add/take × points/wallet). An admin holding one may also have a MONTHLY cap on it (`admin_ledger_limits`;
 * no row or a null cap = unlimited). What was used this month is read from the ledgers themselves — every line the admin wrote
 * (`created_by`) since the first of the month, positive lines counting as "add", negative as "take" — so nothing can drift. The super
 * admin passes every permission and is never capped. Pass a limit check inside a transaction that has locked the admin's row, so
 * two requests cannot both spend the same allowance.
 */
final class AdminLedgerLimits
{
    public const POINTS = 'points';

    public const WALLET = 'wallet';

    public const ADD = 'add';

    public const TAKE = 'take';

    public static function permissionFor(string $ledger, string $direction): string
    {
        return match ([$ledger, $direction]) {
            [self::POINTS, self::ADD] => UserPermissionEnum::ADD_LOYALTY_POINTS,
            [self::POINTS, self::TAKE] => UserPermissionEnum::TAKE_LOYALTY_POINTS,
            [self::WALLET, self::ADD] => UserPermissionEnum::ADD_WALLET_MONEY,
            [self::WALLET, self::TAKE] => UserPermissionEnum::TAKE_WALLET_MONEY,
        };
    }

    /** @return array<int, string> the four permissions, for the admin screen */
    public static function permissions(): array
    {
        return [UserPermissionEnum::ADD_LOYALTY_POINTS, UserPermissionEnum::TAKE_LOYALTY_POINTS, UserPermissionEnum::ADD_WALLET_MONEY, UserPermissionEnum::TAKE_WALLET_MONEY];
    }

    public static function isSuper(User $admin): bool
    {
        return $admin->hasRole(UserRoleEnum::SUPER_ADMIN);
    }

    /**
     * The cap in force (null = unlimited). The admin's OWN row wins (a null there is an explicit "unlimited"); with none, the
     * strictest cap among the roles that grant the permission applies; with no cap anywhere, it is unlimited.
     */
    public static function limitOf(User $admin, string $permission): ?float
    {
        return self::sourceOf($admin, $permission)['limit'];
    }

    /**
     * Where the cap comes from, for the screens: `own` (the admin's row), `role` (a role's default), `none` (nothing set = unlimited).
     *
     * @return array{limit: ?float, source: string}
     */
    public static function sourceOf(User $admin, string $permission): array
    {
        if (self::isSuper($admin)) {
            return ['limit' => null, 'source' => 'super'];
        }

        $own = AdminLedgerLimit::query()->where('user_id', $admin->id)->where('permission', $permission)->first();
        if ($own !== null) {
            return ['limit' => $own->monthly_limit === null ? null : (float) $own->monthly_limit, 'source' => 'own'];
        }

        $caps = RoleLedgerLimit::query()
            ->where('permission', $permission)
            ->whereNotNull('monthly_limit')
            ->whereIn('role_id', $admin->roles()->whereHas('permissions', fn ($q) => $q->where('name', $permission))->pluck('roles.id'))
            ->pluck('monthly_limit');

        return $caps->isEmpty() ? ['limit' => null, 'source' => 'none'] : ['limit' => (float) $caps->min(), 'source' => 'role'];
    }

    /** What this admin has already added / taken this month on a ledger (always a positive figure). */
    public static function usedThisMonth(User $admin, string $ledger, string $direction): float
    {
        $start = Carbon::now(config('app.timezone'))->startOfMonth();
        [$model, $column] = $ledger === self::POINTS ? [LoyaltyTransaction::class, 'points'] : [WalletTransaction::class, 'amount'];

        $query = $model::query()->where('created_by', $admin->id)->where('created_at', '>=', $start);
        $sum = $direction === self::ADD
            ? (float) (clone $query)->where($column, '>', 0)->sum($column)
            : abs((float) (clone $query)->where($column, '<', 0)->sum($column));

        return round($sum, 2);
    }

    /**
     * What the admin may do right now on one ledger+direction.
     *
     * @return array{allowed: bool, limit: ?float, used: float, remaining: ?float}  limit/remaining null = unlimited
     */
    public static function allowance(User $admin, string $ledger, string $direction): array
    {
        $permission = self::permissionFor($ledger, $direction);
        if (! $admin->can($permission)) {
            return ['allowed' => false, 'limit' => null, 'used' => 0.0, 'remaining' => 0.0];
        }

        $limit = self::limitOf($admin, $permission);
        $used = self::usedThisMonth($admin, $ledger, $direction);

        return ['allowed' => true, 'limit' => $limit, 'used' => $used, 'remaining' => $limit === null ? null : max(0.0, round($limit - $used, 2))];
    }

    /**
     * Refuses (403) an admin without the permission, and (a message the caller shows) one whose month's cap would be passed.
     *
     * @param  array<int, array{0: string, 1: string, 2: float|int}>  $legs  [ledger, direction, amount]
     *
     * @throws AuthorizationException   no permission
     * @throws LedgerLimitExceeded      over the monthly cap
     */
    public static function assert(User $admin, array $legs): void
    {
        foreach ($legs as [$ledger, $direction, $amount]) {
            $permission = self::permissionFor($ledger, $direction);
            if (! $admin->can($permission)) {
                throw new AuthorizationException('You do not have the "'.$permission.'" permission.');
            }

            $limit = self::limitOf($admin, $permission);
            if ($limit === null) {
                continue;
            }

            $used = self::usedThisMonth($admin, $ledger, $direction);
            if ($used + abs((float) $amount) > $limit + 0.00001) {
                throw new LedgerLimitExceeded(sprintf(
                    'Your monthly limit for "%s" is %s and you have used %s, so %s more is over it.',
                    $permission, rtrim(rtrim(number_format($limit, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format($used, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format(abs((float) $amount), 2, '.', ''), '0'), '.')
                ));
            }
        }
    }
}
