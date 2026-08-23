<?php

namespace App\Http\Controllers\Admin\Product\Actions\Concerns;

use App\Models\Product;
use App\Models\ProductGallery;
use Illuminate\Support\Facades\Storage;

trait AttachesEditorGalleryImages
{
    /**
     * Turn images uploaded from inside the description editor into gallery rows.
     *
     * The files are already on disk — the editor needs a URL the moment they are
     * dropped in — so only the product link is created here.
     */
    protected function attachEditorGalleryImages(Product $product, array $validated): void
    {
        $paths = array_unique(array_filter(
            array_map('strval', $validated['editor_gallery_paths'] ?? [])
        ));

        if ($paths === []) {
            return;
        }

        $disk = Storage::disk('public');
        $sortOrder = (int) $product->galleries()->max('sort_order');

        foreach ($paths as $path) {
            if (! ProductGallery::isEditorPath($path) || ! $disk->exists($path)) {
                continue;
            }

            // The same image survives repeated saves of the same description.
            if ($product->galleries()->where('image_path', $path)->exists()) {
                continue;
            }

            ProductGallery::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'sort_order' => ++$sortOrder,
            ]);
        }
    }
}
