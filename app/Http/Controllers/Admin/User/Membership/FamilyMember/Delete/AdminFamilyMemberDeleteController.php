<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Delete;

use App\Http\Controllers\Concerns\FindsRelatives;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFamilyMemberDeleteController extends BaseController
{
    use FindsRelatives;
    use ScopesByMembershipCreator;

    /**
     * Delete the specified relative (soft: the member row and its user both).
     */
    public function __invoke(
        Request $request,
        string $user,
        string $membership,
        string $familyMember
    ): RedirectResponse {
        try {
            $relative = $this->findRelative($user, $membership, $familyMember);
            $old = $this->relativeSnapshot($relative);
            $parentId = $relative->parent_membership_id;

            DB::transaction(function () use ($relative) {
                $relative->user?->delete();
                $relative->delete();
            });

            MemberLog::record(
                membershipId: $parentId,
                adminId: Auth::id(),
                action: MemberLog::ACTION_FAMILY_DELETED,
                oldValues: $old,
                newValues: null,
                request: $request,
            );

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete family member', [
                'family_member_id' => $familyMember,
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete family member. Please try again.']);
        }
    }
}
