<?php

namespace App\Http\Controllers\Admin\Facility\Manager;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Puts back a manager removed from the facility form (directly, or as part of
 * the whole facility going to the trash).
 *
 * A manager has no list page of its own — it is only ever edited inline on
 * the facility form — so unlike facilities and branches it has no separate
 * trash screen. Instead the "Removed managers" list on the facility edit page
 * calls this and gets the restored manager back immediately, the same way
 * AdminFacilityManagerSaveController answers a save.
 */
class AdminFacilityManagerRestoreController extends BaseController
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

    public function __invoke(string $facility, int $manager): JsonResponse
    {
        $facility = Facility::where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        $manager = FacilityManager::onlyTrashed()
            ->where('id', $manager)
            ->where('facility_id', $facility->id)
            ->firstOrFail();

        $adminId = Auth::id();
        $request = request();

        try {
            DB::transaction(function () use ($facility, $manager, $adminId, $request) {
                FacilityLog::record(
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_MANAGER_RESTORED,
                    oldValues: ['manager_id' => $manager->id, 'deleted_at' => $manager->deleted_at?->toDateTimeString()],
                    newValues: ['manager_id' => $manager->id, 'deleted_at' => null, 'restored' => true],
                    request: $request,
                );

                $manager->deleted_by = null;
                $manager->restore();
            });
        } catch (\Throwable $exception) {
            Log::error('Failed to restore facility manager', [
                'facility_id' => $facility->id,
                'manager_id' => $manager->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to restore the manager. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Manager restored successfully.',
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => $manager->position,
                'phones' => $manager->phones,
            ],
        ]);
    }
}
