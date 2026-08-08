<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\User\UserPermissionEnum;
use App\Models\MembershipCard;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Scoping for the membership-card-patches (batch generation) area. Mirrors
 * `ScopesByMembershipCreator` row-for-row but reads `membership_card_patches`
 * columns instead.
 *
 * Admins can hold any combination of:
 *   - `view membership card patches` / `create membership card patches`       → full access
 *   - `view own membership card patches` / `create own membership card patches` → cards they created themselves
 *   - `view partner membership card patches` / `create partner membership card patches` → cards whose partner_id matches
 *                                                                             the admin's User::partner_id
 *
 * When neither narrower permission is held (and a full view/create permission
 * is present) no row-level filter applies. Otherwise the visible set is the
 * **union**: created_by = me OR partner_id = mine. Holding both narrower
 * permissions unlocks the partner field on the create form so the admin can
 * create batches for any partner under their creator scope.
 */
trait ScopesByMembershipCardCreator
{
    protected function hasFullAccess(): bool
    {
        $user = Auth::user();
        if ($user === null) return false;
        return $user->hasPermissionTo(UserPermissionEnum::VIEW_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_MEMBERSHIP_CARD_PATCHES);
    }

    protected function scopesCardsToCreator(): bool
    {
        $user = Auth::user();
        if ($user === null) return false;
        if ($this->hasFullAccess()) return false;
        return $user->hasPermissionTo(UserPermissionEnum::VIEW_OWN_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_OWN_MEMBERSHIP_CARD_PATCHES);
    }

    protected function scopesCardsToPartner(): bool
    {
        $user = Auth::user();
        if ($user === null) return false;
        if ($this->hasFullAccess()) return false;
        return $user->hasPermissionTo(UserPermissionEnum::VIEW_PARTNER_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_PARTNER_MEMBERSHIP_CARD_PATCHES);
    }

    protected function isCardScopeRestricted(): bool
    {
        return $this->scopesCardsToCreator() || $this->scopesCardsToPartner();
    }

    /**
     * True when the partner field must be locked on the card create form.
     * Only locks when partner is the sole narrowing scope — admins who also
     * hold an "own" view or create permission keep a free selector.
     */
    protected function cardPartnerLocked(): bool
    {
        return $this->scopesCardsToPartner() && !$this->scopesCardsToCreator();
    }

    protected function currentAdminCardPartnerId(): ?int
    {
        if (!$this->scopesCardsToPartner()) {
            return null;
        }
        $partnerId = Auth::user()?->partner_id;
        return $partnerId !== null ? (int) $partnerId : null;
    }

    /**
     * Apply the union scope (creator OR partner) to a MembershipCard query.
     * A partner-scoped admin with no partner_id attached gets a -1 sentinel
     * so the clause is unsatisfiable rather than silently widening.
     */
    protected function applyCardScope($query)
    {
        $creator = $this->scopesCardsToCreator();
        $partner = $this->scopesCardsToPartner();
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

    protected function cardScopeFilter(): Closure
    {
        return function ($cq) {
            $this->applyCardScope($cq);
        };
    }

    /**
     * 403 if the given card is not in the current admin's union scope.
     */
    protected function assertCanManageCard(MembershipCard $card): void
    {
        if (!$this->isCardScopeRestricted()) {
            return;
        }

        $creator = $this->scopesCardsToCreator();
        $partner = $this->scopesCardsToPartner();
        $partnerId = Auth::user()?->partner_id;

        $matchesCreator = $creator && (int) $card->created_by === (int) Auth::id();
        $matchesPartner = $partner
            && $partnerId !== null
            && (int) $card->partner_id === (int) $partnerId;

        if (!$matchesCreator && !$matchesPartner) {
            throw new AuthorizationException($this->cardScopeViolationMessage());
        }
    }

    private function cardScopeViolationMessage(): string
    {
        return match (true) {
            $this->scopesCardsToCreator() && $this->scopesCardsToPartner()
                => 'You can only manage card batches you created or batches for your partner.',
            $this->scopesCardsToCreator()
                => 'You can only manage card batches you created.',
            $this->scopesCardsToPartner()
                => 'You can only manage card batches for your partner.',
            default => 'You do not have access to this card batch.',
        };
    }
}
