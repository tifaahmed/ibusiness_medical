<?php

namespace App\Http\Controllers\Admin\Product\Store;

use App\Http\Controllers\Admin\Product\Actions\Store\StoreProductAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminProductStoreController extends BaseController
{
    private StoreProductAction $storeAction;

    public function __construct(StoreProductAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    /**
     * Store a newly created product in storage.
     */
    public function __invoke(
        StoreProductRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $product = $this->storeAction->execute($validated);

            Log::info('Product created successfully', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product.list')
                ->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create product', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create product. Please try again.'])
                ->withInput();
        }
    }
}
