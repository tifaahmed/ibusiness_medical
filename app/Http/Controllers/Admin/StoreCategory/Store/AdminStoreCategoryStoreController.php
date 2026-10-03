<?php

namespace App\Http\Controllers\Admin\StoreCategory\Store;

use App\Http\Controllers\Admin\StoreCategory\Actions\Store\StoreStoreCategoryAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\StoreCategory\StoreStoreCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminStoreCategoryStoreController extends BaseController
{
    private StoreStoreCategoryAction $storeAction;

    public function __construct(StoreStoreCategoryAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    /**
     * Store a newly created store category in storage.
     */
    public function __invoke(
        StoreStoreCategoryRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store store category in database
            $storeCategory = $this->storeAction->execute($validated);

            Log::info('Store category created successfully', [
                'store_category_id' => $storeCategory->id,
                'store_category_slug' => $storeCategory->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.store-category.list')
                ->with('success', 'Store category created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create store category', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create store category. Please try again.'])
                ->withInput();
        }
    }
}
