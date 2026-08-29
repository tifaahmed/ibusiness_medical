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
        $metaTitleTranslations = $this->getTranslations('meta_title');
        $metaDescriptionTranslations = $this->getTranslations('meta_description');
        $metaKeywordsTranslations = $this->getTranslations('meta_keywords');

        return [
            'id' => $this->id,
            'name' => $nameTranslations,
            'short_subject' => $shortSubjectTranslations,
            'description' => $descriptionTranslations,
            'slug' => $this->slug,
            'meta_title' => $metaTitleTranslations,
            'meta_description' => $metaDescriptionTranslations,
            'meta_keywords' => $metaKeywordsTranslations,
            'canonical_url' => $this->canonical_url,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'cost_price' => $this->cost_price,
            'profit_price' => $this->profit_price,
            'large_image' => $this->getFirstMediaUrl('large_image'),
            'small_image' => $this->getFirstMediaUrl('small_image'),
            // The real uploaded share image only — not the large-image fallback,
            // so the form's clear button has something to actually clear.
            'og_image' => $this->getFirstMediaUrl('og_image'),
            'gallery' => $this->gallery,
            'product_type_id' => $this->product_type_id,
            'is_visible' => (bool) $this->is_visible,
            'is_accessible' => (bool) $this->is_accessible,
            'is_purchasable' => (bool) $this->is_purchasable,
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
