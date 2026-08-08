<?php

namespace App\Http\Controllers\Admin\Facility\Store;

use App\Http\Controllers\Admin\Facility\Actions\Store\StoreFacilityAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\StoreFacilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityStoreController extends BaseController
{
    private StoreFacilityAction $storeAction;

    public function __construct(StoreFacilityAction $storeAction)
    {
        $this->storeAction = $storeAction;  
    }

    /**
     * Store a newly created facility in storage.
     */
    public function __invoke(
        StoreFacilityRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store facility in database
            $facility = $this->storeAction->execute($validated);

            Log::info('Facility created successfully', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility.list')
                ->with('success', 'Facility created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create facility', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to create facility. Please try again.'])
                ->withInput();
        }
    }
}



