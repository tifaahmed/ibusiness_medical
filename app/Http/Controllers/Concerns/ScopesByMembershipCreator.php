<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\User\UserPermissionEnum;
use App\Models\Membership;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Scoping for the membership area.
 *
 * An admin can hold any combination of:
 *   - `manage memberships`           → full access (overrides everything)
 *   - `manage own memberships`       → memberships they created themselves
 *   - `manage partner memberships`   → memberships whose partner_id matches
 *                                       the admin's own User::partner_id
 *
 * If `manage memberships` is held, no row-level restriction applies.
 * Otherwise the visible set is the **union** of the active narrower scopes:
 *   created_by = admin.id  OR  partner_id = admin.partner_id
 *
 * Filters narrow within that visible set — they cannot widen it.
 */
trait ScopesByMembershipCreator
{
    /**
     * True when the current admin has `manage own memberships` (and not the
     * unrestricted `manage memberships`). Returns true regardless of whether
     * the admin also has `manage partner memberships` — both can apply at once
     * and union together.
     */
    protected function scopesMembershipsToCreator(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::MANAGE_MEMBERSHIPS)) {
            return false;
        }
        return $user->hasPermissionTo(UserPermissionEnum::MANAGE_OWN_MEMBERSHIPS);
    }

    /**
     * True when the current admin has `manage partner memberships` (and not
     * the unrestricted `manage memberships`).
     */
    protected function scopesMembershipsToPartner(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::MANAGE_MEMBERSHIPS)) {
            return false;
        }
        return $user->hasPermissionTo(UserPermissionEnum::MANAGE_PARTNER_MEMBERSHIPS);
    }

    /**
     * True when the current admin has `manage partner member payments` (and not
     * the unrestricted `manage member payments`).
     */
    protected function scopesPaymentsToPartner(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::MANAGE_MEMBER_PAYMENTS)) {
            return false;
        }
        return $user->hasPermissionTo(UserPermissionEnum::MANAGE_PARTNER_MEMBER_PAYMENTS);
    }

    /**
     * Partner id used to scope member payments for the current admin, or null
     * if the admin is not partner-scoped for payments (or has no partner).
     */
    protected function currentAdminPaymentPartnerId(): ?int
    {
        if (!$this->scopesPaymentsToPartner()) {
            return null;
        }
        $partnerId = Auth::user()?->partner_id;
        return $partnerId !== null ? (int) $partnerId : null;
    }

    /**
     * True when ANY row-level restriction applies to the current admin.
     */
    protected function isMembershipScopeRestricted(): bool
    {
        return $this->scopesMembershipsToCreator() || $this->scopesMembershipsToPartner();
    }

    /**
     * True when the partner field must be locked to the admin's partner_id
     * on membership create/edit forms. Only locks when partner is the ONLY
     * narrowing scope — admins who also hold `manage own memberships` keep a
     * free partner selector so they can pick freely for their own memberships.
     */
    protected function membershipPartnerLocked(): bool
    {
        return $this->scopesMembershipsToPartner() && !$this->scopesMembershipsToCreator();
    }

    /**
     * Partner id used to scope memberships for the current admin, or null if
     * the admin is not partner-scoped (or has no partner attached).
     */
    protected function currentAdminPartnerId(): ?int
    {
        if (!$this->scopesMembershipsToPartner()) {
            return null;
        }
        $partnerId = Auth::user()?->partner_id;
        return $partnerId !== null ? (int) $partnerId : null;
    }

    /**
     * Apply the union scope (creator OR partner) to a Membership query in
     * place. Safe to call on an already-restricted query — it composes via
     * AND with whatever you've already added.
     *
     * A partner-scoped admin with no partner_id attached gets a -1 sentinel,
     * so the clause is unsatisfiable rather than silently widening to all
     * memberships of that scope.
     */
    protected function applyMembershipScope($query)
    {
        $creator = $this->scopesMembershipsToCreator();
        $partner = $this->scopesMembershipsToPartner();
        if (!$creator && !$partner) {
            return $query;
        }

        $adminId = Auth::id();
        $partnerId = Auth::user()?->partner_id;

        $query->where(function ($w) use ($creator, $partner, $adminId, $partnerId) {
            if ($creator) {
                $w->orWhere('created_by', $adminId);
            }
            if ($partner) {
                $w->orWhere('partner_id', $partnerId ?? -1);
            }
        });
        return $query;
    }

    /**
     * Closure that applies the union scope to a Membership sub-query. Use
     * inside `whereHas('memberships', $this->membershipScopeFilter())` or as
     * the callback to a `with(['memberships' => ...])` eager load when you
     * also need additional per-call filters layered on top.
     */
    protected function membershipScopeFilter(): Closure
    {
        return function ($mq) {
            $this->applyMembershipScope($mq);
        };
    }

    /**
     * 403 if the target user has no memberships within the current admin's
     * union scope. Admins with no row-level scope (full access) pass through.
     */
    protected function assertCanManageUser(User $user): void
    {
        if (!$this->isMembershipScopeRestricted()) {
            return;
        }

        $matches = $user->memberships()
            ->withTrashed()
            ->where($this->membershipScopeFilter())
            ->exists();

        if (!$matches) {
            throw new AuthorizationException($this->scopeViolationMessage());
        }
    }

    /**
     * 403 if the given membership is not in the current admin's union scope.
     */
    protected function assertCanManageMembership(Membership $membership): void
    {
        if (!$this->isMembershipScopeRestricted()) {
            return;
        }

        $creator = $this->scopesMembershipsToCreator();
        $partner = $this->scopesMembershipsToPartner();
        $partnerId = Auth::user()?->partner_id;

        $matchesCreator = $creator && (int) $membership->created_by === (int) Auth::id();
        $matchesPartner = $partner
            && $partnerId !== null
            && (int) $membership->partner_id === (int) $partnerId;

        if (!$matchesCreator && !$matchesPartner) {
            throw new AuthorizationException($this->scopeViolationMessage());
        }
    }

    private function scopeViolationMessage(): string
    {
        return match (true) {
            $this->scopesMembershipsToCreator() && $this->scopesMembershipsToPartner()
                => 'You can only manage memberships you created or memberships for your partner.',
            $this->scopesMembershipsToCreator()
                => 'You can only manage memberships you created.',
            $this->scopesMembershipsToPartner()
                => 'You can only manage memberships for your partner.',
            default => 'You do not have access to this membership.',
        };
    }
}
