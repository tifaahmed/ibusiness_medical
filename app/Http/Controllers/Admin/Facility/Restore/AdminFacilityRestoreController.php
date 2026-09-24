<?php

namespace App\Http\Controllers\Admin\Facility\Restore;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityRestoreController extends BaseController
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
     * Put a facility back on the list, with the branches and managers that
     * went into the trash with it.
     *
     * Only branches/managers sharing the facility's exact `deleted_at` are
     * restored — one deleted separately, before or since, stays in the trash
     * until it is restored for itself. See AdminFacilityDeleteController.
     */
    public function __invoke(Request $request, string $facilitySlug): RedirectResponse
    {
        $facility = Facility::onlyTrashed()->where('slug', $facilitySlug)->firstOrFail();
        $this->assertOwns($facility);

        /*
         * The raw stored string, not the cast `deleted_at` attribute: a
         * Carbon instance used as a query binding gets reformatted by
         * Connection::prepareBindings() to whole seconds, which would match
         * every row deleted in the same second as this facility rather than
         * only the ones deleted in the same call. See
         * AdminFacilityDeleteController for the write side of this.
         */
        $deletedAtRaw = $facility->getRawOriginal('deleted_at');
        $deletedAtDisplay = $facility->deleted_at?->toDateTimeString();
        $adminId = Auth::id();
        $facilityId = $facility->id;
        $facilitySlugValue = $facility->slug;

        try {
            DB::transaction(function () use ($facilityId, $deletedAtRaw, $deletedAtDisplay, $adminId, $request) {
                $branches = FacilityBranch::onlyTrashed()
                    ->where('facility_id', $facilityId)
                    ->where('deleted_at', $deletedAtRaw)
                    ->get();

                $managers = FacilityManager::onlyTrashed()
                    ->where('facility_id', $facilityId)
                    ->where('deleted_at', $deletedAtRaw)
                    ->get();

                foreach ($branches as $branch) {
                    FacilityBranchLog::record(
                        facilityBranchId: $branch->id,
                        facilityId: $facilityId,
                        adminId: $adminId,
                        action: FacilityBranchLog::ACTION_RESTORED,
                        oldValues: ['deleted_at' => $deletedAtDisplay],
                        newValues: ['deleted_at' => null, 'restored' => true],
                        request: $request,
                    );
                    FacilityLog::record(
                        facilityId: $facilityId,
                        adminId: $adminId,
                        action: FacilityLog::ACTION_BRANCH_RESTORED,
                        oldValues: ['branch_id' => $branch->id, 'deleted_at' => $deletedAtDisplay],
                        newValues: ['branch_id' => $branch->id, 'deleted_at' => null, 'restored' => true],
                        request: $request,
                    );
                }

                foreach ($managers as $manager) {
                    FacilityLog::record(
                        facilityId: $facilityId,
                        adminId: $adminId,
                        action: FacilityLog::ACTION_MANAGER_RESTORED,
                        oldValues: ['manager_id' => $manager->id, 'deleted_at' => $deletedAtDisplay],
                        newValues: ['manager_id' => $manager->id, 'deleted_at' => null, 'restored' => true],
                        request: $request,
                    );
                }

                FacilityBranch::onlyTrashed()
                    ->where('facility_id', $facilityId)
                    ->where('deleted_at', $deletedAtRaw)
                    ->update(['deleted_at' => null, 'deleted_by' => null]);

                FacilityManager::onlyTrashed()
                    ->where('facility_id', $facilityId)
                    ->where('deleted_at', $deletedAtRaw)
                    ->update(['deleted_at' => null, 'deleted_by' => null]);

                FacilityLog::record(
                    facilityId: $facilityId,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_RESTORED,
                    oldValues: ['deleted_at' => $deletedAtDisplay],
                    newValues: ['deleted_at' => null, 'restored' => true],
                    request: $request,
                );

                // A raw update, not $facility->restore(): consistent with the
                // rest of this method, and sidesteps the stale in-memory
                // model entirely.
                Facility::withTrashed()->where('id', $facilityId)->update(['deleted_at' => null, 'deleted_by' => null]);
            });

            Log::info('Facility restored from trash by admin.', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.facility.trash')
                ->with('success', 'Facility restored successfully.');
        } catch (\Throwable $exception) {
            Log::error('Failed to restore facility.', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
                'admin_id' => $adminId,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Could not restore the facility. Please try again.']);
        }
    }
}
