<?php

namespace App\Http\Controllers\Admin\Product\Actions\Update;

use App\Http\Controllers\Admin\Product\Actions\Concerns\AttachesEditorGalleryImages;
use App\Models\Product;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateProductAction
{
    use AttachesEditorGalleryImages;

    /**
     * Execute the action to update a product.
     *
     * @throws \Exception
     */
    public function execute(Product $product, array $validated): Product
    {
        DB::beginTransaction();

        try {
            $product->update([
                'name' => $validated['name'],
                'short_subject' => $validated['short_subject'] ?? null,
                'description' => $validated['description'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'cost_price' => $validated['cost_price'] ?? null,
                'profit_price' => $validated['profit_price'] ?? null,
                'product_type_id' => $validated['product_type_id'] ?? null,
                // Absent means the form never asked: keep what the product already says.
                'is_visible' => Product::normalizeFlag($validated, 'is_visible', $product->is_visible),
                'is_accessible' => Product::normalizeFlag($validated, 'is_accessible', $product->is_accessible),
                'is_purchasable' => Product::normalizeFlag($validated, 'is_purchasable', $product->is_purchasable),
                'admin_note' => $validated['admin_note'] ?? null,
                'banner_config' => Product::normalizeBannerConfig($validated['banner_config'] ?? null),
                // Empty translation maps are stored as null so fallbacks kick in.
                'meta_title' => !empty($validated['meta_title']) ? $validated['meta_title'] : null,
                'meta_description' => !empty($validated['meta_description']) ? $validated['meta_description'] : null,
                'meta_keywords' => !empty($validated['meta_keywords']) ? $validated['meta_keywords'] : null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                // `created_by` is stamped once at creation and never reassigned.
            ]);

            if (isset($validated['large_image'])) {
                $product->clearMediaCollection('large_image');
                $product->addMedia($validated['large_image'])
                    ->toMediaCollection('large_image');
            } elseif (filter_var($validated['remove_large_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $product->clearMediaCollection('large_image');
            }

            if (isset($validated['small_image'])) {
                $product->clearMediaCollection('small_image');
                $product->addMedia($validated['small_image'])
                    ->toMediaCollection('small_image');
            } elseif (filter_var($validated['remove_small_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $product->clearMediaCollection('small_image');
            }

            if (isset($validated['og_image'])) {
                $product->clearMediaCollection('og_image');
                $product->addMedia($validated['og_image'])
                    ->toMediaCollection('og_image');
            } elseif (filter_var($validated['og_image_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $product->clearMediaCollection('og_image');
            }

            // Drop only the gallery images the admin explicitly removed.
            $removedIds = array_filter(array_map('intval', $validated['removed_gallery_ids'] ?? []));

            if ($removedIds !== []) {
                $removed = $product->galleries()->whereIn('id', $removedIds)->get();

                foreach ($removed as $galleryImage) {
                    Storage::disk('public')->delete($galleryImage->image_path);
                    $galleryImage->delete();
                }
            }

            // New uploads are appended to whatever is left.
            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                $sortOrder = (int) $product->galleries()->max('sort_order');

                foreach ($validated['gallery'] as $image) {
                    $path = $image->store("products/gallery/{$product->id}", 'public');

                    ProductGallery::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => ++$sortOrder,
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
