<?php

namespace App\Http\Controllers\Admin\Facility\Branch;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\FacilityBranch\Actions\Store\StoreFacilityBranchAction;
use App\Http\Controllers\Admin\FacilityBranch\Actions\Update\UpdateFacilityBranchAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\SaveFacilityBranchRequest;
use App\Http\Resources\Admin\Facility\Edit\AdminFacilityEditBranchResource;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Saves a single branch straight from the facility form's branch modal.
 *
 * The facility form otherwise only writes branches when the whole facility is
 * submitted; here the admin gets the branch stored (and logged) the moment they
 * press "Add branch" / "Update branch", and the saved row comes back as JSON so
 * the list on the page can show it with its real id.
 */
class AdminFacilityBranchSaveController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(
        private StoreFacilityBranchAction $storeAction,
        private UpdateFacilityBranchAction $updateAction,
    ) {}

    public function __invoke(SaveFacilityBranchRequest $request, string $facility): JsonResponse
    {
        $facility = Facility::where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        $validated = $request->validated();
        $branchId = $validated['id'] ?? null;
        unset($validated['id']);

        // The facility comes from the URL, so the payload can't move a branch.
        $validated['facility_id'] = $facility->id;

        try {
            if ($branchId) {
                $branch = FacilityBranch::where('id', $branchId)
                    ->where('facility_id', $facility->id)
                    ->firstOrFail();

                $branch = $this->updateAction->execute($branch, $validated);
                $created = false;
            } else {
                $branch = $this->storeAction->execute($validated);
                $created = true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to save facility branch from the facility form', [
                'facility_id' => $facility->id,
                'branch_id' => $branchId,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to save the branch. Please try again.',
            ], 500);
        }

        // The branch actions write the branch log; the facility timeline gets its
        // own entry here so it reads the same as a branch saved with the facility.
        FacilityLog::record(
            facilityId: $facility->id,
            adminId: Auth::id(),
            action: $created ? FacilityLog::ACTION_BRANCH_CREATED : FacilityLog::ACTION_BRANCH_UPDATED,
            oldValues: null,
            newValues: ['branch_id' => $branch->id],
            request: $request,
        );

        return response()->json([
            'created' => $created,
            'message' => $created ? 'Branch created successfully.' : 'Branch updated successfully.',
            'branch' => (new AdminFacilityEditBranchResource($branch->fresh()->load('creator')))->toArray($request),
        ]);
    }
}
