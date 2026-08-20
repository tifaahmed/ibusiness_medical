<?php

namespace App\Http\Controllers\Admin\Product\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Product\Actions\Update\UpdateProductAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminProductUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCTS; }

    private UpdateProductAction $updateAction;

    public function __construct(UpdateProductAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified product.
     */
    public function __invoke(
        UpdateProductRequest $request,
        string $product,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $productModel = Product::where('slug', $product)->firstOrFail();
            $this->assertOwns($productModel);

            $updatedProduct = $this->updateAction->execute($productModel, $validated);

            Log::info('Product updated successfully', [
                'product_id' => $updatedProduct->id,
                'product_slug' => $updatedProduct->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product.list')
                ->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update product', [
                'product_slug' => $product,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update product. Please try again.'])
                ->withInput();
        }
    }
}
