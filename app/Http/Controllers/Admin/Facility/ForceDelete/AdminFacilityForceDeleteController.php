<?php

namespace App\Http\Controllers\Admin\Facility\ForceDelete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminFacilityForceDeleteController extends BaseController
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

    /**
     * Erase one facility, and every branch/manager still attached to it, for
     * good.
     *
     * Only reachable for a facility already in the trash: a permanent delete
     * is the second of two deliberate steps.
     *
     * Not wrapped in a transaction with the log, and the log is written
     * first: `forceDelete()` removes media files off disk (logo, cover,
     * gallery, contract) via Spatie's own delete hook, which no rollback can
     * undo, so a failed erase should still leave a record of the attempt.
     * `facility_logs`/`facility_branch_logs` rows survive — their FK is
     * nulled, not cascaded — so the audit trail outlives the facility.
     */
    public function __invoke(Request $request, string $facilitySlug): RedirectResponse
    {
        $facility = Facility::onlyTrashed()->where('slug', $facilitySlug)->firstOrFail();
        $this->assertOwns($facility);

        $facilityId = $facility->id;
        $adminId = Auth::id();

        try {
            FacilityLog::record(
                facilityId: $facilityId,
                adminId: $adminId,
                action: FacilityLog::ACTION_FORCE_DELETED,
                oldValues: [
                    'name' => $facility->getTranslations('name'),
                    'slug' => $facility->slug,
                    'facility_type_id' => $facility->facility_type_id,
                ],
                newValues: ['permanently_deleted' => true],
                request: $request,
            );

            FacilityBranch::withTrashed()->where('facility_id', $facilityId)->forceDelete();
            FacilityManager::withTrashed()->where('facility_id', $facilityId)->forceDelete();
            $facility->forceDelete();

            Log::info('Facility permanently deleted by admin.', [
                'facility_id' => $facilityId,
                'facility_slug' => $facilitySlug,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.facility.trash')
                ->with('success', 'Facility permanently deleted.');
        } catch (\Throwable $exception) {
            Log::error('Failed to permanently delete facility.', [
                'facility_id' => $facilityId,
                'facility_slug' => $facilitySlug,
                'admin_id' => $adminId,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Could not permanently delete the facility. Please try again.']);
        }
    }
}
