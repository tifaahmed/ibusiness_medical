<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Delete;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MembershipUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminMembershipUsageDeleteController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request, MembershipUsage $membershipUsage): RedirectResponse
    {
        $membershipUsage->loadMissing('membership');
        if ($membershipUsage->membership) {
            $this->assertCanManageMembership($membershipUsage->membership);
        }

        $usageId = $membershipUsage->id;

        try {
            DB::beginTransaction();

            $membershipUsage->delete();

            DB::commit();

            Log::info('MembershipUsage deleted', ['membership_usage_id' => $usageId]);

            return redirect()->route('admin.membership-usage.list')
                ->with('success', 'Membership usage deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete MembershipUsage', [
                'membership_usage_id' => $usageId,
                'error_message'       => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete membership usage. Please try again.']);
        }
    }
}
