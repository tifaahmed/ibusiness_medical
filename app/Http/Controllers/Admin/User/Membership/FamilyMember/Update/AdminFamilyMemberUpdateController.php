<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Update;

use App\Actions\Membership\UpdateRelative;
use App\Http\Controllers\Concerns\FindsRelatives;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\FamilyMember\UpdateFamilyMemberRequest;
use App\Models\MemberLog;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminFamilyMemberUpdateController extends BaseController
{
    use FindsRelatives;
    use ScopesByMembershipCreator;

    public function __construct(
        private MediaService $mediaService,
        private UpdateRelative $updateRelative,
    ) {}

    /**
     * Update the specified relative.
     */
    public function __invoke(
        UpdateFamilyMemberRequest $request,
        string $user,
        string $membership,
        string $familyMember
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $relative = $this->findRelative($user, $membership, $familyMember);
            $old = $this->relativeSnapshot($relative);

            $relative = $this->updateRelative->handle($relative, $validated);

            if ($request->hasFile('photo')) {
                $this->mediaService->uploadImage($relative, $request->file('photo'), 'photo');
            }

            $new = $this->relativeSnapshot($relative->fresh(['user']));

            if ($old !== $new) {
                MemberLog::record(
                    membershipId: $relative->parent_membership_id,
                    adminId: Auth::id(),
                    action: MemberLog::ACTION_FAMILY_UPDATED,
                    oldValues: $old,
                    newValues: $new,
                    request: $request,
                );
            }

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member updated successfully.');
        } catch (ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
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
}
