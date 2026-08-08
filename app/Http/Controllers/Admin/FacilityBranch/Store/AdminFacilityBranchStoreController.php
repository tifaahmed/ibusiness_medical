<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Store;

use App\Http\Controllers\Admin\FacilityBranch\Actions\Store\StoreFacilityBranchAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\FacilityBranch\StoreFacilityBranchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFacilityBranchStoreController extends BaseController
{
    private StoreFacilityBranchAction $storeAction;

    public function __construct(StoreFacilityBranchAction $storeAction)
    {
        $this->storeAction = $storeAction;  
    }

    /**
     * Store a newly created facility branch in storage.
     */
    public function __invoke(
        StoreFacilityBranchRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store facility branch in database
            $facilityBranch = $this->storeAction->execute($validated);

            Log::info('Facility branch created successfully', [
                'facility_branch_id' => $facilityBranch->id,
                'facility_branch_slug' => $facilityBranch->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility-branch.list')
                ->with('success', 'Facility branch created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create facility branch', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to create facility branch. Please try again.'])
                ->withInput();
        }
    }
}



