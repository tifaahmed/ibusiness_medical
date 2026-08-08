<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Update;

use App\Http\Controllers\Admin\MembershipUsage\Actions\Update\UpdateMembershipUsageAction;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\MembershipUsage\UpdateMembershipUsageRequest;
use App\Models\MembershipUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminMembershipUsageUpdateController extends BaseController
{
    use ScopesByMembershipCreator;

    private UpdateMembershipUsageAction $updateAction;

    public function __construct(UpdateMembershipUsageAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(UpdateMembershipUsageRequest $request, MembershipUsage $membershipUsage): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $membershipUsage->loadMissing('membership');
            if ($membershipUsage->membership) {
                $this->assertCanManageMembership($membershipUsage->membership);
            }

            $this->updateAction->execute($membershipUsage, $validated);

            Log::info('MembershipUsage updated', ['membership_usage_id' => $membershipUsage->id]);

            return redirect()->route('admin.membership-usage.list')
                ->with('success', 'Membership usage updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update MembershipUsage', ['error_message' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to update membership usage. Please try again.'])->withInput();
        }
    }
}
