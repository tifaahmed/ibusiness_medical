<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Delete;

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

class AdminFacilityBranchDeleteController extends BaseController
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
     * Move the specified facility branch to the trash.
     */
    public function __invoke(Request $request, string $facilityBranchSlug): RedirectResponse
    {
        try {
            $facilityBranch = FacilityBranch::where('slug', $facilityBranchSlug)->firstOrFail();
            $this->assertOwns($facilityBranch);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Facility branch not found for deletion', [
                'slug' => $facilityBranchSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Facility branch not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching facility branch for deletion', [
                'slug' => $facilityBranchSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the facility branch.']);
        }

        // Store data for logging before deletion
        $facilityBranchId = $facilityBranch->id;
        $facilityBranchSlugValue = $facilityBranch->slug;
        $facilityIdForLog = $facilityBranch->facility_id;
        $snapshot = $this->snapshot($facilityBranch);
        $adminId = Auth::id();

        try {
            DB::beginTransaction();

            FacilityBranchLog::record(
                facilityBranchId: $facilityBranchId,
                facilityId: $facilityIdForLog,
                adminId: $adminId,
                action: FacilityBranchLog::ACTION_DELETED,
                oldValues: $snapshot,
                newValues: null,
                request: $request,
            );
            FacilityLog::record(
                facilityId: $facilityIdForLog,
                adminId: $adminId,
                action: FacilityLog::ACTION_BRANCH_DELETED,
                oldValues: $snapshot,
                newValues: null,
                request: $request,
            );

            $facilityBranch->deleted_by = $adminId;
            $facilityBranch->save();
            $facilityBranch->delete();

            DB::commit();

            Log::info('Facility branch moved to trash by admin', [
                'facility_branch_id' => $facilityBranchId,
                'facility_branch_slug' => $facilityBranchSlugValue,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-branch.list')
                ->with('success', 'Facility branch moved to trash. It can be restored from the trash page.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete facility branch', [
                'facility_branch_id' => $facilityBranchId,
                'facility_branch_slug' => $facilityBranchSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete facility branch. Please try again.']);
        }
    }

    private function snapshot(FacilityBranch $branch): array
    {
        return [
            'branch_id' => $branch->id,
            'facility_id' => $branch->facility_id,
            'name' => $this->normalizeTranslatable($branch->getRawOriginal('name')),
            'address' => $this->normalizeTranslatable($branch->getRawOriginal('address')),
            'phone' => $branch->phone,
            'governorate_id' => $branch->governorate_id,
            'city_id' => $branch->city_id,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
        ];
    }

    private function normalizeTranslatable(mixed $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
        $filtered = array_filter($decoded, fn ($v) => $v !== null && $v !== '');

        return $filtered === [] ? null : $filtered;
    }
}
