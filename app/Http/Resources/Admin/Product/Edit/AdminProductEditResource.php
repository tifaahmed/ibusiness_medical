<?php

namespace App\Http\Resources\Admin\Product\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nameTranslations = $this->getTranslations('name');
        $shortSubjectTranslations = $this->getTranslations('short_subject');
        $descriptionTranslations = $this->getTranslations('description');

        return [
            'id' => $this->id,
            'name' => $nameTranslations,
            'short_subject' => $shortSubjectTranslations,
            'description' => $descriptionTranslations,
            'slug' => $this->slug,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'cost_price' => $this->cost_price,
            'profit_price' => $this->profit_price,
            'large_image' => $this->getFirstMediaUrl('large_image'),
            'small_image' => $this->getFirstMediaUrl('small_image'),
            'gallery' => $this->gallery,
            'product_type_id' => $this->product_type_id,
            'admin_note' => $this->admin_note,
            'banner_config' => $this->banner_config,
            'creator_id' => $this->created_by,
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'icon' => $tag->icon,
                    'color' => $tag->color,
                ]);
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
