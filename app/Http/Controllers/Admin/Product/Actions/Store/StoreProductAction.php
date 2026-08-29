<?php

namespace App\Http\Controllers\Admin\Product\Actions\Store;

use App\Http\Controllers\Admin\Product\Actions\Concerns\AttachesEditorGalleryImages;
use App\Models\Product;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreProductAction
{
    use AttachesEditorGalleryImages;

    /**
     * Execute the action to store a product.
     *
     * @throws \Exception
     */
    public function execute(array $validated): Product
    {
        DB::beginTransaction();

        try {
            $product = Product::create([
                'name' => $validated['name'],
                'short_subject' => $validated['short_subject'] ?? null,
                'description' => $validated['description'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'cost_price' => $validated['cost_price'] ?? null,
                'profit_price' => $validated['profit_price'] ?? null,
                'product_type_id' => $validated['product_type_id'] ?? null,
                // A new product is listed, openable and sellable unless said otherwise.
                'is_visible' => Product::normalizeFlag($validated, 'is_visible', true),
                'is_accessible' => Product::normalizeFlag($validated, 'is_accessible', true),
                'is_purchasable' => Product::normalizeFlag($validated, 'is_purchasable', true),
                'admin_note' => $validated['admin_note'] ?? null,
                'banner_config' => Product::normalizeBannerConfig($validated['banner_config'] ?? null),
                // Empty translation maps are stored as null so fallbacks kick in.
                'meta_title' => !empty($validated['meta_title']) ? $validated['meta_title'] : null,
                'meta_description' => !empty($validated['meta_description']) ? $validated['meta_description'] : null,
                'meta_keywords' => !empty($validated['meta_keywords']) ? $validated['meta_keywords'] : null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                // Stamped once, from the backend: the product belongs to whoever created it.
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

            if (isset($validated['og_image'])) {
                $product->addMedia($validated['og_image'])
                    ->toMediaCollection('og_image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $index => $image) {
                    $path = $image->store("products/gallery/{$product->id}", 'public');

                    ProductGallery::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => $index,
                    ]);
                }
            }

            $this->attachEditorGalleryImages($product, $validated);

            // An empty tag selection is dropped from multipart bodies, so the form
            // sends `sync_tags` to say the (possibly empty) selection is authoritative.
            if (array_key_exists('tag_ids', $validated)
                || filter_var($validated['sync_tags'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
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
