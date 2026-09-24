<?php

namespace App\Http\Controllers\Admin\FacilityBranch\ForceDelete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminFacilityBranchForceDeleteController extends BaseController
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
     * Erase one branch for good. Only reachable from the trash — a permanent
     * delete is the second of two deliberate steps.
     *
     * The log is written before the delete and not sharing its transaction,
     * matching AdminFacilityForceDeleteController: `facility_branch_logs`
     * survives with its FK nulled, the record outliving the row it describes.
     */
    public function __invoke(Request $request, string $facilityBranchSlug): RedirectResponse
    {
        $branch = FacilityBranch::onlyTrashed()->where('slug', $facilityBranchSlug)->firstOrFail();
        $this->assertOwns($branch);

        $branchId = $branch->id;
        $facilityId = $branch->facility_id;
        $adminId = Auth::id();

        try {
            FacilityBranchLog::record(
                facilityBranchId: $branchId,
                facilityId: $facilityId,
                adminId: $adminId,
                action: FacilityBranchLog::ACTION_FORCE_DELETED,
                oldValues: [
                    'name' => $branch->getTranslations('name'),
                    'slug' => $branch->slug,
                ],
                newValues: ['permanently_deleted' => true],
                request: $request,
            );

            $branch->forceDelete();

            Log::info('Facility branch permanently deleted by admin.', [
                'facility_branch_id' => $branchId,
                'facility_id' => $facilityId,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.facility-branch.trash')
                ->with('success', 'Facility branch permanently deleted.');
        } catch (\Throwable $exception) {
            Log::error('Failed to permanently delete facility branch.', [
                'facility_branch_id' => $branchId,
                'admin_id' => $adminId,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Could not permanently delete the facility branch. Please try again.']);
        }
    }
}
