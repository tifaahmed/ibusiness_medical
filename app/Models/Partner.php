<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Partner extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use MediaImageTrait;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'short_description',
        'description',
        'card_x',
        'card_y',
        'card_scale',
        'created_by',
    ];

    protected $casts = [
        'card_x' => 'float',
        'card_y' => 'float',
        'card_scale' => 'float',
        'deleted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(PartnerOffer::class);
    }

    public function getHeaderImageAttribute(): string
    {
        $media = $this->getFirstMedia('header_image');
        return $media ? $media->getUrl() : '';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('header_image')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }
}
