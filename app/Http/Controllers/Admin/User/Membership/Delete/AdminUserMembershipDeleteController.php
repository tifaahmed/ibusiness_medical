<?php

namespace App\Http\Controllers\Admin\User\Membership\Delete;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserMembershipDeleteController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Remove the specified user and membership from storage.
     */
    public function __invoke(Request $request, string $userSlug): RedirectResponse
    {
        try {
            // Fetch user with memberships in a single database query
            $user = User::with('memberships')->where('slug', $userSlug)->firstOrFail();
            $this->assertCanManageUser($user);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found for deletion', [
                'slug' => $userSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'User not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching user for deletion', [
                'slug' => $userSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'An error occurred while fetching the user.']);
        }

        // Store data for logging before deletion
        $userId = $user->id;
        $userEmail = $user->email;
        $userName = $user->name;
        $adminId = Auth::id();
        $restricted = $this->isMembershipScopeRestricted();

        if ($restricted) {
            // Restricted admins can only delete memberships within their union
            // scope: ones they created and/or ones for their partner. The user
            // row itself stays alive if any out-of-scope memberships remain.
            $creatorScoped = $this->scopesMembershipsToCreator();
            $partnerScoped = $this->scopesMembershipsToPartner();
            $adminPartnerId = Auth::user()?->partner_id;
            $membershipsToDelete = $user->memberships
                ->filter(function ($m) use ($creatorScoped, $partnerScoped, $adminId, $adminPartnerId) {
                    if ($creatorScoped && (int) $m->created_by === (int) $adminId) {
                        return true;
                    }
                    if ($partnerScoped && $adminPartnerId !== null && (int) $m->partner_id === (int) $adminPartnerId) {
                        return true;
                    }
                    return false;
                })
                ->values();
        } else {
            $membershipsToDelete = $user->memberships;
        }
        $activeMembership = $membershipsToDelete->firstWhere('is_active', true);
        $membershipId = $activeMembership?->id;
        $membershipNumber = $activeMembership?->membership_number;
        $membershipsCount = $membershipsToDelete->count();

        try {
            DB::beginTransaction();

            foreach ($membershipsToDelete as $membership) {
                MemberLog::record(
                    membershipId: $membership->id,
                    adminId: $adminId,
                    action: MemberLog::ACTION_DELETED,
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
                $membership->delete();
            }

            // Only delete the user record if every membership was removed —
            // i.e. the broader-permission admin or a scoped admin who owns all
            // the user's memberships. Otherwise, leave the user intact so other
            // creators' memberships remain accessible.
            if ($user->memberships()->count() === 0) {
                $user->delete();
            }

            DB::commit();

            Log::info('User and memberships soft deleted successfully', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'membership_id' => $membershipId,
                'membership_number' => $membershipNumber,
                'memberships_count' => $membershipsCount,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.user.membership.list')
                ->with('success', 'User and memberships moved to trash successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete user and memberships', [
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
            
            return back()->withErrors(['error' => 'Failed to delete user and memberships. Please try again.']);
        }
    }
}

