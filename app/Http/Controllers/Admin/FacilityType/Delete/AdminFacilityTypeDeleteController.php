<?php

namespace App\Http\Controllers\Admin\FacilityType\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityTypeDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Remove the specified facility type from storage.
     */
    public function __invoke(Request $request, string $facilityTypeSlug): RedirectResponse
    {
        try {
            $facilityType = FacilityType::where('slug', $facilityTypeSlug)->firstOrFail();
            $this->assertOwns($facilityType);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Facility type not found for deletion', [
                'slug' => $facilityTypeSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Facility type not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching facility type for deletion', [
                'slug' => $facilityTypeSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the facility type.']);
        }

        // Store data for logging before deletion
        $facilityTypeId = $facilityType->id;
        $facilityTypeName = $facilityType->name;
        $facilityTypeSlugValue = $facilityType->slug;
        $facilitiesCount = $facilityType->facilities()->count();

        // Check if facility type has facilities
        if ($facilitiesCount > 0) {
            return back()->withErrors(['error' => 'Cannot delete facility type with associated facilities. Please remove or reassign facilities first.']);
        }

        try {
            DB::beginTransaction();

            // Delete the facility type
            $facilityType->delete();

            DB::commit();

            Log::info('Facility type deleted successfully', [
                'facility_type_id' => $facilityTypeId,
                'facility_type_slug' => $facilityTypeSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-type.list')
                ->with('success', 'Facility type deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete facility type', [
                'facility_type_id' => $facilityTypeId,
                'facility_type_slug' => $facilityTypeSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete facility type. Please try again.']);
        }
    }
}



