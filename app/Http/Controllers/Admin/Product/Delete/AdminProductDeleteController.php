<?php

namespace App\Http\Controllers\Admin\Product\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCTS; }

    /**
     * Remove the specified product from storage.
     */
    public function __invoke(Request $request, string $productSlug): RedirectResponse
    {
        try {
            $product = Product::where('slug', $productSlug)->firstOrFail();
            $this->assertOwns($product);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Product not found for deletion', [
                'slug' => $productSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Product not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching product for deletion', [
                'slug' => $productSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the product.']);
        }

        $productId = $product->id;
        $productSlugValue = $product->slug;

        try {
            DB::beginTransaction();

            $product->delete();

            DB::commit();

            Log::info('Product deleted successfully', [
                'product_id' => $productId,
                'product_slug' => $productSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.product.list')
                ->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete product', [
                'product_id' => $productId,
                'product_slug' => $productSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete product. Please try again.']);
        }
    }
}
