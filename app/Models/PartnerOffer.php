<?php

namespace App\Models;

use App\Enums\PartnerOffer\OperatorEnum;
use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PartnerOffer extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use MediaImageTrait;
    use SoftDeletes;

    protected $fillable = [
        'partner_id',
        'created_by',
        'title',
        'short_description',
        'description',
        'old_price',
        'new_price',
        'phone_number',
        'operator',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'operator' => OperatorEnum::class,
        'deleted_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PartnerOfferRequest::class);
    }

    public function getHeaderImageAttribute(): string
    {
        $media = $this->getFirstMedia('header_image');
        return $media ? $media->getUrl() : '';
    }

    public function getSmallImageAttribute(): string
    {
        $media = $this->getFirstMedia('small_image');
        return $media ? $media->getUrl() : '';
    }

    public function getMobileHeaderImageAttribute(): string
    {
        $media = $this->getFirstMedia('mobile_header_image');
        return $media ? $media->getUrl() : '';
    }

    public function getMobileSmallImageAttribute(): string
    {
        $media = $this->getFirstMedia('mobile_small_image');
        return $media ? $media->getUrl() : '';
    }
}
