<?php

namespace App\Http\Controllers\Admin\User\Membership\ForceDelete;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserMembershipForceDeleteController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Permanently delete the specified user and memberships from storage.
     */
    public function __invoke(Request $request, string $user): RedirectResponse
    {
        // Fetch trashed user with memberships
        $user = User::onlyTrashed()->with(['memberships' => function ($query) {
            $query->withTrashed();
        }])->where('slug', $user)->firstOrFail();

        // own/partner admins can only force-delete users whose memberships
        // fall within their union scope. Throws 403 otherwise.
        $this->assertCanManageUser($user);

        // Store data for logging before deletion
        $userId = $user->id;
        $userEmail = $user->email;
        $userName = $user->name;
        $activeMembership = $user->memberships()->withTrashed()->where('is_active', true)->first();
        $membershipId = $activeMembership?->id;
        $membershipNumber = $activeMembership?->membership_number;
        $membershipsCount = $user->memberships()->withTrashed()->count();

        try {
            DB::beginTransaction();

            $adminId = Auth::id();
            foreach ($user->memberships()->withTrashed()->get() as $membership) {
                MemberLog::record(
                    membershipId: $membership->id,
                    adminId: $adminId,
                    action: MemberLog::ACTION_FORCE_DELETED,
                    oldValues: [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'membership_number' => $membership->membership_number,
                        'is_active' => (bool) $membership->is_active,
                        'is_visible' => (bool) $membership->is_visible,
                    ],
                    newValues: null,
                    request: $request,
                );
            }

            // Permanently delete all memberships
            $user->memberships()->withTrashed()->forceDelete();

            // Permanently delete the user
            $user->forceDelete();

            DB::commit();

            Log::info('User and memberships permanently deleted', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'membership_id' => $membershipId,
                'membership_number' => $membershipNumber,
                'memberships_count' => $membershipsCount,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.user.membership.trash')
                ->with('success', 'User and memberships permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to permanently delete user and memberships', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'membership_id' => $membershipId,
                'membership_number' => $membershipNumber,
                'memberships_count' => $membershipsCount,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to permanently delete user and memberships. Please try again.']);
        }
    }
}
