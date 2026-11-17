<?php

namespace App\Http\Controllers\Admin\User\Membership\Trials;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/** A member's history with trials (JSON for the pop-up on the edit and the show pages). Read-only. */
class AdminUserMembershipTrialsController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(string $user): JsonResponse
    {
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $user = User::withTrashed()
            ->with(['memberships' => function ($q) use ($restricted, $scopeFilter) {
                $q->withTrashed();
                if ($restricted) {
                    $scopeFilter($q);
                }
            }])
            ->where('slug', $user)
            ->firstOrFail();

        $this->assertCanManageUser($user);

        $cards = $user->memberships->map(function (Membership $m) {
            $rows = $m->trials()->with('admin:id,name')->get();

            return [
                'membership_id' => $m->id,
                'membership_number' => $m->membership_number,
                'is_trial' => $m->isTrial(),
                'locked' => $m->trialLocked(),
                'trial_ends_at' => $m->trial_ends_at?->toDateString(),
                'expiration_date' => $m->expiration_date?->toDateString(),
                'days_left' => $m->isTrial() ? $m->trialDaysLeft() : 0,
                /* "Extended N times" = how many extend events the customer has had. */
                'extensions' => $rows->where('event', 'extended')->count(),
                'events' => $rows->map(fn ($r) => [
                    'id' => $r->id,
                    'event' => $r->event,
                    'starts_on' => $r->starts_on?->toDateString(),
                    'ends_on' => $r->ends_on?->toDateString(),
                    'previous_ends_on' => $r->previous_ends_on?->toDateString(),
                    'expires_on' => $r->expires_on?->toDateString(),
                    'previous_expires_on' => $r->previous_expires_on?->toDateString(),
                    'days' => $r->days,
                    'by' => $r->admin?->name,
                    'at' => $r->created_at?->format('Y-m-d H:i'),
                    'note' => $r->note,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => ['user' => $user->name, 'cards' => $cards]]);
    }
}
