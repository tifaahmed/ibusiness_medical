<?php

namespace App\Http\Controllers\Admin\StoreCategory\Actions\Store;

use App\Models\StoreCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreStoreCategoryAction
{
    /**
     * Execute the action to store a store category.
     *
     * @param array $validated
     * @return StoreCategory
     * @throws \Exception
     */
    public function execute(array $validated): StoreCategory
    {
        DB::beginTransaction();

        try {
            // Create the store category
            $storeCategory = StoreCategory::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Store category created successfully', [
                'store_category_id' => $storeCategory->id,
                'store_category_slug' => $storeCategory->slug,
            ]);

            return $storeCategory;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create store category', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
