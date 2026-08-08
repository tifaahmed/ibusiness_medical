<?php

namespace App\Http\Controllers\Admin\User\Membership\ActiveHistory;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberActiveHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipActiveHistoryController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request, string $user): Response
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

        $membershipIds = $user->memberships->pluck('id')->all();

        $historiesQuery = MemberActiveHistory::query()
            ->with('changer:id,name,email')
            ->whereIn('membership_id', $membershipIds)
            ->latest('created_at');

        $histories = $historiesQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $histories->getCollection()->transform(function (MemberActiveHistory $history) {
            return [
                'id' => $history->id,
                'membership_id' => $history->membership_id,
                'old_is_active' => $history->old_is_active,
                'new_is_active' => $history->new_is_active,
                'changed_by' => $history->changed_by,
                'created_at' => $history->created_at?->toIso8601String(),
                'changer' => $history->changer ? [
                    'id' => $history->changer->id,
                    'name' => $history->changer->name,
                    'email' => $history->changer->email,
                ] : null,
            ];
        });

        return Inertia::render('Admin/User/Membership/ActiveHistory', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'slug' => $user->slug,
            ],
            'histories' => $histories->toArray(),
        ]);
    }
}
