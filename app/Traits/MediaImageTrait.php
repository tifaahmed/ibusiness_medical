<?php

namespace App\Traits;

trait MediaImageTrait
{
    // avatar
    public function getAvatarAttribute()
    {
        $media = $this->getFirstMedia('avatar');
        if ($media) {
            return $media->getUrl();
        } else {
            return '';
        }
    }

    // image
    public function getImageAttribute()
    {
        $media = $this->getFirstMedia('image');
        if ($media) {
            return $media->getUrl();
        } else {
            return '';
        }
    }

    // mobile_image
    public function getMobileImageAttribute(): string
    {
        $media = $this->getFirstMedia('mobile_image');
        return $media ? $media->getUrl() : '';
    }

    // thumbnail
    public function getThumbnailAttribute()
    {
        $media = $this->getFirstMedia('thumbnail');
        if ($media) {
            return $media->getUrl();
        } else {
            return '';
        }
    }

    // mobile_thumbnail
    public function getMobileThumbnailAttribute(): string
    {
        $media = $this->getFirstMedia('mobile_thumbnail');
        return $media ? $media->getUrl() : '';
    }

    // logo
    public function getLogoAttribute(): string
    {
        $media = $this->getFirstMedia('logo');
        return $media ? $media->getUrl() : '';
    }

    // mobile_logo
    public function getMobileLogoAttribute(): string
    {
        $media = $this->getFirstMedia('mobile_logo');
        return $media ? $media->getUrl() : '';
    }

    // gallery
    public function getGalleryAttribute(): array
    {
        return $this->getMedia('gallery')
            ->map(fn($m) => ['id' => $m->id, 'url' => $m->getUrl()])
            ->toArray();
    }
}
