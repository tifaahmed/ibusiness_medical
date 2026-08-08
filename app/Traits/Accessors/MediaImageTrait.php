<?php

namespace App\Traits\Accessors;

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
}
