<?php

namespace App\Http\Controllers\Admin\StoreCategory\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\StoreCategory\Actions\Update\UpdateStoreCategoryAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\StoreCategory\UpdateStoreCategoryRequest;
use App\Models\StoreCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminStoreCategoryUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_STORE_CATEGORIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES; }

    private UpdateStoreCategoryAction $updateAction;

    public function __construct(UpdateStoreCategoryAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified store category.
     */
    public function __invoke(
        UpdateStoreCategoryRequest $request,
        string $storeCategory,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $storeCategoryModel = StoreCategory::where('slug', $storeCategory)->firstOrFail();
            $this->assertOwns($storeCategoryModel);

            // Execute the action to update store category in database
            $updatedStoreCategory = $this->updateAction->execute($storeCategoryModel, $validated);

            Log::info('Store category updated successfully', [
                'store_category_id' => $updatedStoreCategory->id,
                'store_category_slug' => $updatedStoreCategory->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.store-category.list')
                ->with('success', 'Store category updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update store category', [
                'store_category_slug' => $storeCategory,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update store category. Please try again.'])
                ->withInput();
        }
    }
}
