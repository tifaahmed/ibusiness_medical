<?php

namespace App\Http\Controllers\Admin\ProductType\Actions\Store;

use App\Models\ProductType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreProductTypeAction
{
    /**
     * Execute the action to store a product type.
     *
     * @param array $validated
     * @return ProductType
     * @throws \Exception
     */
    public function execute(array $validated): ProductType
    {
        DB::beginTransaction();

        try {
            // Create the product type
            $productType = ProductType::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Product type created successfully', [
                'product_type_id' => $productType->id,
                'product_type_slug' => $productType->slug,
            ]);

            return $productType;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create product type', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
