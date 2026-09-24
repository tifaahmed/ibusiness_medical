<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Restore;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityBranchRestoreController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    /**
     * Put one branch back on the list.
     *
     * If its facility is also in the trash the branch reappears there, out of
     * every list, until the facility itself is restored — restoring a branch
     * never resurrects its facility.
     */
    public function __invoke(Request $request, string $facilityBranchSlug): RedirectResponse
    {
        $branch = FacilityBranch::onlyTrashed()->where('slug', $facilityBranchSlug)->firstOrFail();
        $this->assertOwns($branch);

        $adminId = Auth::id();

        try {
            DB::transaction(function () use ($branch, $adminId, $request) {
                FacilityBranchLog::record(
                    facilityBranchId: $branch->id,
                    facilityId: $branch->facility_id,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_RESTORED,
                    oldValues: ['deleted_at' => $branch->deleted_at?->toDateTimeString()],
                    newValues: ['deleted_at' => null, 'restored' => true],
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $branch->facility_id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_RESTORED,
                    oldValues: ['branch_id' => $branch->id, 'deleted_at' => $branch->deleted_at?->toDateTimeString()],
                    newValues: ['branch_id' => $branch->id, 'deleted_at' => null, 'restored' => true],
                    request: $request,
                );

                $branch->deleted_by = null;
                $branch->restore();
            });

            Log::info('Facility branch restored from trash by admin.', [
                'facility_branch_id' => $branch->id,
                'facility_branch_slug' => $branch->slug,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.facility-branch.trash')
                ->with('success', 'Facility branch restored successfully.');
        } catch (\Throwable $exception) {
            Log::error('Failed to restore facility branch.', [
                'facility_branch_id' => $branch->id,
                'facility_branch_slug' => $branch->slug,
                'admin_id' => $adminId,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Could not restore the facility branch. Please try again.']);
        }
    }
}
