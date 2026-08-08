<?php

namespace App\Http\Controllers\Admin\FacilityType\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\FacilityType\Actions\Update\UpdateFacilityTypeAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\FacilityType\UpdateFacilityTypeRequest;
use App\Models\FacilityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityTypeUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    private UpdateFacilityTypeAction $updateAction;

    public function __construct(UpdateFacilityTypeAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified facility type.
     */
    public function __invoke(
        UpdateFacilityTypeRequest $request,
        string $facilityType,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $facilityType = FacilityType::where('slug', $facilityType)->firstOrFail();
            $this->assertOwns($facilityType);

            // Execute the action to update facility type in database
            $updatedFacilityType = $this->updateAction->execute($facilityType, $validated);

            Log::info('Facility type updated successfully', [
                'facility_type_id' => $updatedFacilityType->id,
                'facility_type_slug' => $updatedFacilityType->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-type.list')
                ->with('success', 'Facility type updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update facility type', [
                'facility_type_slug' => $facilityType,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update facility type. Please try again.'])
                ->withInput();
        }
    }
}



