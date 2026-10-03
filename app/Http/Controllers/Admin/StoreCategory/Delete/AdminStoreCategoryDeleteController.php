<?php

namespace App\Http\Controllers\Admin\StoreCategory\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\StoreCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminStoreCategoryDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_STORE_CATEGORIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES; }

    /**
     * Remove the specified store category from storage.
     */
    public function __invoke(Request $request, string $storeCategorySlug): RedirectResponse
    {
        try {
            $storeCategory = StoreCategory::where('slug', $storeCategorySlug)->firstOrFail();
            $this->assertOwns($storeCategory);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Store category not found for deletion', [
                'slug' => $storeCategorySlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Store category not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching store category for deletion', [
                'slug' => $storeCategorySlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the store category.']);
        }

        // Store data for logging before deletion
        $storeCategoryId = $storeCategory->id;
        $storeCategorySlugValue = $storeCategory->slug;

        try {
            DB::beginTransaction();

            // Delete the store category
            $storeCategory->delete();

            DB::commit();

            Log::info('Store category deleted successfully', [
                'store_category_id' => $storeCategoryId,
                'store_category_slug' => $storeCategorySlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.store-category.list')
                ->with('success', 'Store category deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete store category', [
                'store_category_id' => $storeCategoryId,
                'store_category_slug' => $storeCategorySlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete store category. Please try again.']);
        }
    }
}
