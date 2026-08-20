<?php

namespace App\Http\Controllers\Admin\ProductType\Actions\Update;

use App\Models\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProductTypeAction
{
    /**
     * Execute the action to update a product type.
     *
     * @param ProductType $productType
     * @param array $validated
     * @return ProductType
     * @throws \Exception
     */
    public function execute(ProductType $productType, array $validated): ProductType
    {
        DB::beginTransaction();

        try {
            // Update the product type
            $productType->update([
                'name' => $validated['name'],
            ]);

            $productType->refresh();

            DB::commit();

            Log::info('Product type updated successfully', [
                'product_type_id' => $productType->id,
                'product_type_slug' => $productType->slug,
            ]);

            return $productType;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update product type', [
                'product_type_id' => $productType->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
