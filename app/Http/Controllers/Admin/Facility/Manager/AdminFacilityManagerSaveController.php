<?php

namespace App\Http\Controllers\Admin\Facility\Manager;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\SaveFacilityManagerRequest;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Saves a single manager straight from the facility form's manager modal.
 *
 * The facility form otherwise only writes managers when the whole facility is
 * submitted; here the admin gets the manager stored (and logged) the moment
 * they press "Add manager" / "Update manager", and the saved row comes back as
 * JSON so the list on the page can show it with its real id — which is also
 * what stops the later facility save from writing a second copy.
 */
class AdminFacilityManagerSaveController extends BaseController
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

    public function __invoke(SaveFacilityManagerRequest $request, string $facility): JsonResponse
    {
        $facility = Facility::where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        $validated = $request->validated();
        $managerId = $validated['id'] ?? null;

        // Already normalised to typed entries by the request; "no phones" has
        // always been NULL in this column rather than an empty list.
        $phones = $validated['phones'] ?? [];

        $attributes = [
            'name' => $validated['name'],
            'position' => $validated['position'] ?? null,
            'phones' => $phones === [] ? null : $phones,
        ];

        try {
            if ($managerId) {
                // Scoped to this facility, so an id from elsewhere cannot be
                // steered into this facility's manager list.
                $manager = FacilityManager::where('id', $managerId)
                    ->where('facility_id', $facility->id)
                    ->firstOrFail();

                $old = $this->snapshot($manager);
                $manager->update($attributes);
                $created = false;
            } else {
                $old = null;
                $manager = FacilityManager::create([
                    'facility_id' => $facility->id,
                    'created_by' => Auth::id(),
                    ...$attributes,
                ]);
                $created = true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to save facility manager from the facility form', [
                'facility_id' => $facility->id,
                'manager_id' => $managerId,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to save the manager. Please try again.',
            ], 500);
        }

        FacilityLog::record(
            facilityId: $facility->id,
            adminId: Auth::id(),
            action: $created ? FacilityLog::ACTION_MANAGER_CREATED : FacilityLog::ACTION_MANAGER_UPDATED,
            oldValues: $old,
            newValues: $this->snapshot($manager->fresh()),
            request: $request,
        );

        return response()->json([
            'created' => $created,
            'message' => $created ? 'Manager created successfully.' : 'Manager updated successfully.',
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => $manager->position,
                'phones' => $manager->phones,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FacilityManager $manager): array
    {
        return [
            'name' => $manager->name,
            'position' => $manager->position,
            'phones' => $manager->phones,
        ];
    }
}
