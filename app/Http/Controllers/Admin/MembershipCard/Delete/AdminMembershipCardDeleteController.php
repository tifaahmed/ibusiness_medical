<?php

namespace App\Http\Controllers\Admin\MembershipCard\Delete;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMembershipCardDeleteController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function __invoke(Request $request, MembershipCard $card): RedirectResponse
    {
        $this->assertCanManageCard($card);
        $ids = $card->membership_ids ?: [];

        // Not-completed (null completed_at) memberships in this batch are still
        // placeholders — hard-delete them (and their placeholder users) instead
        // of leaving soft-deleted stubs around. Completed memberships are left intact.
        $purged = 0;
        if (!empty($ids)) {
            $purged = DB::transaction(function () use ($ids) {
                $notCompleted = Membership::withTrashed()
                    ->whereIn('id', $ids)
                    ->whereNull('completed_at')
                    ->get(['id', 'user_id']);

                $userIds = $notCompleted->pluck('user_id')->filter()->unique()->all();
                $membershipIds = $notCompleted->pluck('id')->all();

                if (!empty($membershipIds)) {
                    Membership::withTrashed()->whereIn('id', $membershipIds)->forceDelete();
                }
                if (!empty($userIds)) {
                    // Only purge users whose name is still empty — i.e. they
                    // were never filled in by the admin. A real user whose
                    // (different) membership happens to be in this batch is
                    // protected by the name check.
                    User::whereIn('id', $userIds)
                        ->whereNull('name')
                        ->delete();
                }

                return count($membershipIds);
            });
        }

        $card->clearMediaCollection('pdf');
        $card->delete();

        $msg = $purged > 0
            ? "Batch deleted. {$purged} not-completed membership(s) were permanently removed."
            : 'Batch deleted.';

        return redirect()
            ->route('admin.membership-card-patches.list')
            ->with('success', $msg);
    }
}
