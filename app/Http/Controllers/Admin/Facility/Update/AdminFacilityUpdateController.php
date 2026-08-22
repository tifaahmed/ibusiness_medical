<?php

namespace App\Http\Controllers\Admin\Facility\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Facility\Actions\Update\UpdateFacilityAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\UpdateFacilityRequest;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityUpdateController extends BaseController
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

    private UpdateFacilityAction $updateAction;

    public function __construct(UpdateFacilityAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified facility.
     */
    public function __invoke(
        UpdateFacilityRequest $request,
        string $facility,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $facility = Facility::where('slug', $facility)->firstOrFail();
            $this->assertOwns($facility);

            // Execute the action to update facility in database
            $updatedFacility = $this->updateAction->execute($facility, $validated);

            Log::info('Facility updated successfully', [
                'facility_id' => $updatedFacility->id,
                'facility_slug' => $updatedFacility->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // "Save & Stay" returns to the edit form; otherwise go back to the list.
            $redirectTo = $request->boolean('stay')
                ? route('admin.facility.edit', ['facility' => $updatedFacility->slug])
                : route('admin.facility.list');

            return redirect()->to($redirectTo)
                ->with('success', 'Facility updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update facility', [
                'facility_slug' => $facility,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update facility. Please try again.'])
                ->withInput();
        }
    }
}
