<?php

namespace App\Http\Controllers\Admin\FacilityType\Actions\Update;

use App\Models\FacilityType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFacilityTypeAction
{
    /**
     * Execute the action to update a facility type.
     *
     * @param FacilityType $facilityType
     * @param array $validated
     * @return FacilityType
     * @throws \Exception
     */
    public function execute(FacilityType $facilityType, array $validated): FacilityType
    {
        DB::beginTransaction();

        try {
            // Update the facility type
            $facilityType->update([
                'name' => $validated['name'],
            ]);

            $facilityType->refresh();

            DB::commit();

            Log::info('Facility type updated successfully', [
                'facility_type_id' => $facilityType->id,
                'facility_type_slug' => $facilityType->slug,
            ]);

            return $facilityType;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update facility type', [
                'facility_type_id' => $facilityType->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}



