<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Actions\Update;

use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Support\PhoneNumbers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFacilityBranchAction
{
    /**
     * Execute the action to update a facility branch.
     *
     * @throws \Exception
     */
    public function execute(FacilityBranch $facilityBranch, array $validated): FacilityBranch
    {
        DB::beginTransaction();

        try {
            $oldSnapshot = $this->snapshot($facilityBranch);

            // One entry per number, each carrying its type. Accepts the flat
            // strings older callers still send as well as the form's entries.
            $phone = PhoneNumbers::entries($validated['phone'] ?? null);

            // Update the facility branch
            $facilityBranch->update([
                'facility_id' => $validated['facility_id'],
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                // Not sent (an older caller): keep what is stored. Sent empty: clear it.
                'area_id' => array_key_exists('area_id', $validated) ? $validated['area_id'] : $facilityBranch->area_id,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'google_location_url' => $validated['google_location_url'] ?? null,
                'name' => $validated['name'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $phone,
            ]);

            $facilityBranch->refresh();
            $newSnapshot = $this->snapshot($facilityBranch);

            FacilityBranchLog::record(
                facilityBranchId: $facilityBranch->id,
                facilityId: $facilityBranch->facility_id,
                adminId: Auth::id(),
                action: FacilityBranchLog::ACTION_UPDATED,
                oldValues: $oldSnapshot,
                newValues: $newSnapshot,
                request: request(),
            );

            // Mirrored onto the facility's own timeline too, so a branch
            // updated from the standalone branch form reads the same as one
            // updated from the facility form's modal.
            FacilityLog::record(
                facilityId: $facilityBranch->facility_id,
                adminId: Auth::id(),
                action: FacilityLog::ACTION_BRANCH_UPDATED,
                oldValues: $oldSnapshot,
                newValues: $newSnapshot,
                request: request(),
            );

            DB::commit();

            Log::info('Facility branch updated successfully', [
                'facility_branch_id' => $facilityBranch->id,
                'facility_branch_slug' => $facilityBranch->slug,
            ]);

            return $facilityBranch;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update facility branch', [
                'facility_branch_id' => $facilityBranch->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
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
            'area_id' => $branch->area_id,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_location_url' => $branch->google_location_url,
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
