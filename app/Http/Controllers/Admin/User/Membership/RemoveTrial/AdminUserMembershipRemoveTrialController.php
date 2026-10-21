<?php

namespace App\Http\Controllers\Admin\User\Membership\RemoveTrial;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Takes the "trial" mark off a member's card by hand — for a member who paid outside the app,
 * or whom customer service decided to treat as a full member.
 *
 * Only the mark changes (`plan` trial -> full): the expiry date, `is_paid` and everything else stay
 * as they are, and are edited on the member form like any other card. Paying through the app does
 * this on its own (`ActivateSubscription`), so this is the manual twin of that step.
 * Idempotent: a card that is not on trial is left alone.
 */
class AdminUserMembershipRemoveTrialController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request, string $userSlug): RedirectResponse
    {
        $user = User::query()->where('slug', $userSlug)->firstOrFail();
        $this->assertCanManageUser($user);

        $membership = $user->memberships()->where('plan', Membership::PLAN_TRIAL)->latest('id')->first();

        if ($membership === null) {
            return back()->with('success', __('admin.member_list.filters.trial_already_removed'));
        }

        $membership->forceFill(['plan' => Membership::PLAN_FULL])->save();

        MemberLog::record(
            membershipId: $membership->id,
            adminId: Auth::id(),
            action: MemberLog::ACTION_UPDATED,
            oldValues: ['plan' => Membership::PLAN_TRIAL],
            newValues: ['plan' => Membership::PLAN_FULL],
            request: $request,
        );

        return back()->with('success', __('admin.member_list.filters.trial_removed'));
    }
}
