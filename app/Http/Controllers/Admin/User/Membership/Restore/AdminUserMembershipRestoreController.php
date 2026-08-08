<?php

namespace App\Http\Controllers\Admin\User\Membership\Restore;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserMembershipRestoreController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Restore the specified user and memberships from trash.
     */
    public function __invoke(Request $request, string $user): RedirectResponse
    {
        // Fetch trashed user with memberships
        $user = User::onlyTrashed()->with(['memberships' => function ($query) {
            $query->withTrashed();
        }])->where('slug', $user)->firstOrFail();

        // own/partner admins can only restore users whose memberships fall
        // within their union scope. Throws 403 otherwise.
        $this->assertCanManageUser($user);

        // Store data for logging
        $userId = $user->id;
        $userEmail = $user->email;
        $userName = $user->name;
        $membershipsCount = $user->memberships()->withTrashed()->count();

        try {
            DB::beginTransaction();

            // Restore all memberships
            $user->memberships()->withTrashed()->restore();

            // Restore the user
            $user->restore();

            $adminId = Auth::id();
            foreach ($user->memberships()->get() as $membership) {
                MemberLog::record(
                    membershipId: $membership->id,
                    adminId: $adminId,
                    action: MemberLog::ACTION_RESTORED,
                    oldValues: null,
                    newValues: [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'membership_number' => $membership->membership_number,
                        'is_active' => (bool) $membership->is_active,
                        'is_visible' => (bool) $membership->is_visible,
                    ],
                    request: $request,
                );
            }

            DB::commit();

            Log::info('User and memberships restored successfully', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'memberships_count' => $membershipsCount,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.user.membership.trash')
                ->with('success', 'User and memberships restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to restore user and memberships', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'memberships_count' => $membershipsCount,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to restore user and memberships. Please try again.']);
        }
    }
}
