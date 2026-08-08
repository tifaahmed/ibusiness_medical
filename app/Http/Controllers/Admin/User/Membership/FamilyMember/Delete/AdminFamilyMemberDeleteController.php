<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Delete;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FamilyMember;
use App\Models\MemberLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminFamilyMemberDeleteController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Delete the specified family member.
     */
    public function __invoke(
        Request $request,
        string $user,
        string $membership,
        string $familyMember
    ): RedirectResponse {
        try {
            // Find the family member
            $familyMemberModel = FamilyMember::with('membership')->whereHas('membership.user', function ($query) use ($user) {
                $query->where('slug', $user);
            })->whereHas('membership', function ($query) use ($membership) {
                $query->where('slug', $membership);
            })->where('id', $familyMember)->firstOrFail();

            if ($familyMemberModel->membership) {
                $this->assertCanManageMembership($familyMemberModel->membership);
            }

            $familyMemberId = $familyMemberModel->id;
            $name = $familyMemberModel->name;
            $oldSnapshot = $this->snapshot($familyMemberModel);
            $membershipId = $familyMemberModel->membership_id;

            // Delete the family member (soft delete)
            $familyMemberModel->delete();

            // Audit log
            MemberLog::record(
                membershipId: $membershipId,
                adminId: Auth::id(),
                action: MemberLog::ACTION_FAMILY_DELETED,
                oldValues: $oldSnapshot,
                newValues: null,
                request: $request,
            );

            Log::info('Family member deleted successfully', [
                'family_member_id' => $familyMemberId,
                'membership_id' => $membershipId,
                'name' => $name,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member deleted successfully.');
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

    private function snapshot(FamilyMember $fm): array
    {
        return [
            'family_member_id' => $fm->id,
            'name' => $fm->name,
            'relationship' => $fm->relationship?->value,
            'date_of_birth' => optional($fm->date_of_birth)->toDateString(),
            'phone' => $fm->phone,
            'email' => $fm->email,
            'is_active' => (bool) $fm->is_active,
        ];
    }
}
