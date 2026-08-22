<?php

namespace App\Http\Resources\Admin\NewsTicker\Show;

use App\Http\Resources\Admin\Concerns\ResolvesTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminNewsTickerShowResource extends JsonResource
{
    use ResolvesTranslations;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->translationMap('title'),
            'description' => $this->translationMap('description'),
            'category' => $this->category,
            // Media-library upload first, with the legacy `image_url` column as
            // the fallback for rows imported before uploads existed.
            'image_url' => $this->image ?: $this->image_url,
            'mobile_image_url' => $this->mobile_image ?: ($this->image ?: $this->image_url),
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
