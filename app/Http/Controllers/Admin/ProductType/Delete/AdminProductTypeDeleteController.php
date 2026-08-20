<?php

namespace App\Http\Controllers\Admin\ProductType\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductTypeDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCT_TYPES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCT_TYPES; }

    /**
     * Remove the specified product type from storage.
     */
    public function __invoke(Request $request, string $productTypeSlug): RedirectResponse
    {
        try {
            $productType = ProductType::where('slug', $productTypeSlug)->firstOrFail();
            $this->assertOwns($productType);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Product type not found for deletion', [
                'slug' => $productTypeSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Product type not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching product type for deletion', [
                'slug' => $productTypeSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the product type.']);
        }

        // Store data for logging before deletion
        $productTypeId = $productType->id;
        $productTypeSlugValue = $productType->slug;

        try {
            DB::beginTransaction();

            // Delete the product type
            $productType->delete();

            DB::commit();

            Log::info('Product type deleted successfully', [
                'product_type_id' => $productTypeId,
                'product_type_slug' => $productTypeSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product-type.list')
                ->with('success', 'Product type deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete product type', [
                'product_type_id' => $productTypeId,
                'product_type_slug' => $productTypeSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete product type. Please try again.']);
        }
    }
}
