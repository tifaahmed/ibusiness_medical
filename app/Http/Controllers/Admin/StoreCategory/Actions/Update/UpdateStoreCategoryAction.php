<?php

namespace App\Http\Controllers\Admin\StoreCategory\Actions\Update;

use App\Models\StoreCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStoreCategoryAction
{
    /**
     * Execute the action to update a store category.
     *
     * @param StoreCategory $storeCategory
     * @param array $validated
     * @return StoreCategory
     * @throws \Exception
     */
    public function execute(StoreCategory $storeCategory, array $validated): StoreCategory
    {
        DB::beginTransaction();

        try {
            // Update the store category
            $storeCategory->update([
                'name' => $validated['name'],
            ]);

            $storeCategory->refresh();

            DB::commit();

            Log::info('Store category updated successfully', [
                'store_category_id' => $storeCategory->id,
                'store_category_slug' => $storeCategory->slug,
            ]);

            return $storeCategory;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update store category', [
                'store_category_id' => $storeCategory->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
