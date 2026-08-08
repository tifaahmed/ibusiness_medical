<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Update;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\FamilyMember\UpdateFamilyMemberRequest;
use App\Models\FamilyMember;
use App\Models\MemberLog;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminFamilyMemberUpdateController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Update the specified family member.
     */
    public function __invoke(
        UpdateFamilyMemberRequest $request,
        string $user,
        string $membership,
        string $familyMember
    ): RedirectResponse {
        $validated = $request->validated();

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

            $oldSnapshot = $this->snapshot($familyMemberModel);

            // Update the family member
            $familyMemberModel->update([
                'name' => $validated['name'],
                'relationship' => $validated['relationship'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Handle photo upload if provided
            if ($request->hasFile('photo')) {
                $this->mediaService->uploadImage($familyMemberModel, $request->file('photo'), 'photo');
            }

            $newSnapshot = $this->snapshot($familyMemberModel->fresh());

            // Audit log (only if something actually changed)
            if ($oldSnapshot !== $newSnapshot) {
                MemberLog::record(
                    membershipId: $familyMemberModel->membership_id,
                    adminId: Auth::id(),
                    action: MemberLog::ACTION_FAMILY_UPDATED,
                    oldValues: $oldSnapshot,
                    newValues: $newSnapshot,
                    request: $request,
                );
            }

            Log::info('Family member updated successfully', [
                'family_member_id' => $familyMemberModel->id,
                'membership_id' => $familyMemberModel->membership_id,
                'name' => $familyMemberModel->name,
                'relationship' => $familyMemberModel->relationship->value,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update family member', [
                'family_member_id' => $familyMember,
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update family member. Please try again.'])
                ->withInput();
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
