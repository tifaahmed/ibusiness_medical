<?php

namespace App\Http\Controllers\Admin\Product\Actions\Store;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreProductAction
{
    /**
     * Execute the action to store a product.
     *
     * @param array $validated
     * @return Product
     * @throws \Exception
     */
    public function execute(array $validated): Product
    {
        DB::beginTransaction();

        try {
            $product = Product::create([
                'name' => $validated['name'],
                'short_subject' => $validated['short_subject'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'product_type_id' => $validated['product_type_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['large_image'])) {
                $product->addMedia($validated['large_image'])
                    ->toMediaCollection('large_image');
            }

            if (isset($validated['small_image'])) {
                $product->addMedia($validated['small_image'])
                    ->toMediaCollection('small_image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $product->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            if (array_key_exists('tag_ids', $validated)) {
                $product->tags()->sync($validated['tag_ids'] ?? []);
            }

            DB::commit();

            Log::info('Product created successfully', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
            ]);

            return $product;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create product', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
