<?php

namespace App\Http\Controllers\Admin\ProductType\Store;

use App\Http\Controllers\Admin\ProductType\Actions\Store\StoreProductTypeAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\ProductType\StoreProductTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminProductTypeStoreController extends BaseController
{
    private StoreProductTypeAction $storeAction;

    public function __construct(StoreProductTypeAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    /**
     * Store a newly created product type in storage.
     */
    public function __invoke(
        StoreProductTypeRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store product type in database
            $productType = $this->storeAction->execute($validated);

            Log::info('Product type created successfully', [
                'product_type_id' => $productType->id,
                'product_type_slug' => $productType->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product-type.list')
                ->with('success', 'Product type created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create product type', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create product type. Please try again.'])
                ->withInput();
        }
    }
}
