<?php

namespace App\Http\Resources\Api\V1\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsTickerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'image_url' => $this->mobile_image ?: ($this->image ?: $this->image_url),
        ];
    }
}
