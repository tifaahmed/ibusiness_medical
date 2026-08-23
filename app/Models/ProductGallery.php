<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGallery extends Model
{
    /**
     * Where images uploaded from inside the description editor land. They are
     * stored before the product exists (create form), so the path cannot be
     * keyed by product id.
     */
    public const EDITOR_DIRECTORY = 'products/gallery/editor';

    protected $table = 'product_gallery';

    protected $fillable = [
        'product_id',
        'image_path',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Guard against a client claiming any arbitrary disk path as a gallery image:
     * only paths the editor upload endpoint produced are accepted.
     */
    public static function isEditorPath(string $path): bool
    {
        return str_starts_with($path, self::EDITOR_DIRECTORY.'/')
            && ! str_contains($path, '..');
    }
}
