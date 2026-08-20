<?php

namespace App\Http\Controllers\Admin\ProductType\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\ProductType\Actions\Update\UpdateProductTypeAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\ProductType\UpdateProductTypeRequest;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminProductTypeUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCT_TYPES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCT_TYPES; }

    private UpdateProductTypeAction $updateAction;

    public function __construct(UpdateProductTypeAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified product type.
     */
    public function __invoke(
        UpdateProductTypeRequest $request,
        string $productType,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $productTypeModel = ProductType::where('slug', $productType)->firstOrFail();
            $this->assertOwns($productTypeModel);

            // Execute the action to update product type in database
            $updatedProductType = $this->updateAction->execute($productTypeModel, $validated);

            Log::info('Product type updated successfully', [
                'product_type_id' => $updatedProductType->id,
                'product_type_slug' => $updatedProductType->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product-type.list')
                ->with('success', 'Product type updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update product type', [
                'product_type_slug' => $productType,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update product type. Please try again.'])
                ->withInput();
        }
    }
}
