<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Store;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\FamilyMember\StoreFamilyMemberRequest;
use App\Models\FamilyMember;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminFamilyMemberStoreController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Store a newly created family member.
     */
    public function __invoke(
        StoreFamilyMemberRequest $request,
        string $user,
        string $membership
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Find the membership
            $membershipModel = Membership::whereHas('user', function ($query) use ($user) {
                $query->where('slug', $user);
            })->where('slug', $membership)->firstOrFail();

            $this->assertCanManageMembership($membershipModel);

            // Create the family member
            $familyMember = FamilyMember::create([
                'membership_id' => $membershipModel->id,
                'name' => $validated['name'],
                'relationship' => $validated['relationship'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Handle photo upload if provided
            if ($request->hasFile('photo')) {
                $this->mediaService->uploadImage($familyMember, $request->file('photo'), 'photo');
            }

            // Audit log
            MemberLog::record(
                membershipId: $membershipModel->id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_FAMILY_CREATED,
                oldValues: null,
                newValues: $this->snapshot($familyMember->fresh()),
                request: $request,
            );

            Log::info('Family member created successfully', [
                'family_member_id' => $familyMember->id,
                'membership_id' => $membershipModel->id,
                'user_id' => $membershipModel->user_id,
                'name' => $familyMember->name,
                'relationship' => $familyMember->relationship->value,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create family member', [
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create family member. Please try again.'])
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
