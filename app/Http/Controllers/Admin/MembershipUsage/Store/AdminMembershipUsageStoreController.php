<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Store;

use App\Http\Controllers\Admin\MembershipUsage\Actions\Store\StoreMembershipUsageAction;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\MembershipUsage\StoreMembershipUsageRequest;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminMembershipUsageStoreController extends BaseController
{
    use ScopesByMembershipCreator;

    private StoreMembershipUsageAction $storeAction;

    public function __construct(StoreMembershipUsageAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    public function __invoke(StoreMembershipUsageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            if (!empty($validated['membership_id'])) {
                $membership = Membership::find($validated['membership_id']);
                if ($membership) {
                    $this->assertCanManageMembership($membership);
                }
            }

            $usage = $this->storeAction->execute($validated);

            Log::info('MembershipUsage created', ['membership_usage_id' => $usage->id]);

            return redirect()->route('admin.membership-usage.list')
                ->with('success', 'Membership usage created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create MembershipUsage', ['error_message' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to create membership usage. Please try again.'])->withInput();
        }
    }
}
