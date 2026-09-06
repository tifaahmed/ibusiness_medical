<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Actions\Store;

use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Support\PhoneNumbers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreFacilityBranchAction
{
    /**
     * Execute the action to store a facility branch.
     *
     * @throws \Exception
     */
    public function execute(array $validated): FacilityBranch
    {
        DB::beginTransaction();

        try {
            // One entry per number, each carrying its type. Accepts the flat
            // strings older callers still send as well as the form's entries.
            $phone = PhoneNumbers::entries($validated['phone'] ?? null);

            // Create the facility branch
            $facilityBranch = FacilityBranch::create([
                'facility_id' => $validated['facility_id'],
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'google_location_url' => $validated['google_location_url'] ?? null,
                'name' => $validated['name'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $phone,
                'created_by' => Auth::id(),
            ]);

            FacilityBranchLog::record(
                facilityBranchId: $facilityBranch->id,
                facilityId: $facilityBranch->facility_id,
                adminId: Auth::id(),
                action: FacilityBranchLog::ACTION_CREATED,
                oldValues: null,
                newValues: $this->snapshot($facilityBranch->fresh()),
                request: request(),
            );

            DB::commit();

            Log::info('Facility branch created successfully', [
                'facility_branch_id' => $facilityBranch->id,
                'facility_branch_slug' => $facilityBranch->slug,
            ]);

            return $facilityBranch;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create facility branch', [
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
