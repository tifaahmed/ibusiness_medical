<?php

namespace App\Http\Controllers\Admin\FacilityType\Store;

use App\Http\Controllers\Admin\FacilityType\Actions\Store\StoreFacilityTypeAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\FacilityType\StoreFacilityTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityTypeStoreController extends BaseController
{
    private StoreFacilityTypeAction $storeAction;

    public function __construct(StoreFacilityTypeAction $storeAction)
    {
        $this->storeAction = $storeAction;  
    }

    /**
     * Store a newly created facility type in storage.
     */
    public function __invoke(
        StoreFacilityTypeRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store facility type in database
            $facilityType = $this->storeAction->execute($validated);

            Log::info('Facility type created successfully', [
                'facility_type_id' => $facilityType->id,
                'facility_type_slug' => $facilityType->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-type.list')
                ->with('success', 'Facility type created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create facility type', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to create facility type. Please try again.'])
                ->withInput();
        }
    }
}



