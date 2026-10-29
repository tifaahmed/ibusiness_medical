<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Store;

use App\Actions\Membership\CreateRelative;
use App\Http\Controllers\Concerns\FindsRelatives;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\FamilyMember\StoreFamilyMemberRequest;
use App\Models\Membership;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminFamilyMemberStoreController extends BaseController
{
    use FindsRelatives;
    use ScopesByMembershipCreator;

    public function __construct(
        private MediaService $mediaService,
        private CreateRelative $createRelative,
    ) {}

    /**
     * Add a relative to a primary card: it becomes a member of its own
     * (`CreateRelative`), with the primary's address and links.
     */
    public function __invoke(
        StoreFamilyMemberRequest $request,
        string $user,
        string $membership
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $primary = Membership::whereHas('user', function ($query) use ($user) {
                $query->where('slug', $user);
            })->where('slug', $membership)->firstOrFail();

            $this->assertCanManageMembership($primary);

            $relative = $this->createRelative->handle($primary, $validated, Membership::ADDED_BY_ADMIN, Auth::id(), $request);

            if ($request->hasFile('photo')) {
                $this->mediaService->uploadImage($relative, $request->file('photo'), 'photo');
            }

            Log::info('Family member created successfully', [
                'relative_membership_id' => $relative->id,
                'membership_id' => $primary->id,
                'relationship' => $relative->relationship,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Family member created successfully.');
        } catch (ValidationException $e) {
            throw $e;
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
}
