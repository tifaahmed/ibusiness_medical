<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StoreGallery extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPES = [self::TYPE_IMAGE, self::TYPE_VIDEO];

    protected $fillable = [
        'store_id',
        'media_path',
        'type',
        'sort_order',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->media_path);
    }
}
