<?php

namespace App\Http\Controllers\Admin\FacilityType\Actions\Store;

use App\Models\FacilityType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreFacilityTypeAction
{
    /**
     * Execute the action to store a facility type.
     *
     * @param array $validated
     * @return FacilityType
     * @throws \Exception
     */
    public function execute(array $validated): FacilityType
    {
        DB::beginTransaction();

        try {
            // Create the facility type
            $facilityType = FacilityType::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Facility type created successfully', [
                'facility_type_id' => $facilityType->id,
                'facility_type_slug' => $facilityType->slug,
            ]);

            return $facilityType;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create facility type', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}



