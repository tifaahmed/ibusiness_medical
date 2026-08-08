<?php

namespace App\Http\Controllers\Admin\Governorate\Store;

use App\Http\Controllers\Admin\Governorate\Actions\Store\StoreGovernorateAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Governorate\StoreGovernorateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminGovernorateStoreController extends BaseController
{
    private StoreGovernorateAction $storeAction;

    public function __construct(StoreGovernorateAction $storeAction)
    {
        $this->storeAction = $storeAction;  
    }

    /**
     * Store a newly created governorate in storage.
     */
    public function __invoke(
        StoreGovernorateRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store governorate in database
            $governorate = $this->storeAction->execute($validated);

            Log::info('Governorate created successfully', [
                'governorate_id' => $governorate->id,
                'governorate_slug' => $governorate->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.governorate.list')
                ->with('success', 'Governorate created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create governorate', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to create governorate. Please try again.'])
                ->withInput();
        }
    }
}



