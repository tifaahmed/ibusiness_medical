<?php

namespace App\Http\Controllers\Admin\Product\Actions\Update;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProductAction
{
    /**
     * Execute the action to update a product.
     *
     * @param Product $product
     * @param array $validated
     * @return Product
     * @throws \Exception
     */
    public function execute(Product $product, array $validated): Product
    {
        DB::beginTransaction();

        try {
            $product->update([
                'name' => $validated['name'],
                'short_subject' => $validated['short_subject'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'product_type_id' => $validated['product_type_id'] ?? null,
            ]);

            if (isset($validated['large_image'])) {
                $product->clearMediaCollection('large_image');
                $product->addMedia($validated['large_image'])
                    ->toMediaCollection('large_image');
            }

            if (isset($validated['small_image'])) {
                $product->clearMediaCollection('small_image');
                $product->addMedia($validated['small_image'])
                    ->toMediaCollection('small_image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                $product->clearMediaCollection('gallery');
                foreach ($validated['gallery'] as $image) {
                    $product->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            if (array_key_exists('tag_ids', $validated)) {
                $product->tags()->sync($validated['tag_ids'] ?? []);
            }

            $product->refresh();

            DB::commit();

            Log::info('Product updated successfully', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
            ]);

            return $product;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update product', [
                'product_id' => $product->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
