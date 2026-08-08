<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\FacilityBranch\Actions\Update\UpdateFacilityBranchAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\FacilityBranch\UpdateFacilityBranchRequest;
use App\Models\FacilityBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityBranchUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITY_BRANCHES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES; }

    private UpdateFacilityBranchAction $updateAction;

    public function __construct(UpdateFacilityBranchAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified facility branch.
     */
    public function __invoke(
        UpdateFacilityBranchRequest $request,
        string $facilityBranch,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $facilityBranch = FacilityBranch::where('slug', $facilityBranch)->firstOrFail();
            $this->assertOwns($facilityBranch);

            // Execute the action to update facility branch in database
            $updatedFacilityBranch = $this->updateAction->execute($facilityBranch, $validated);

            Log::info('Facility branch updated successfully', [
                'facility_branch_id' => $updatedFacilityBranch->id,
                'facility_branch_slug' => $updatedFacilityBranch->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-branch.list')
                ->with('success', 'Facility branch updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update facility branch', [
                'facility_branch_slug' => $facilityBranch,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update facility branch. Please try again.'])
                ->withInput();
        }
    }
}



