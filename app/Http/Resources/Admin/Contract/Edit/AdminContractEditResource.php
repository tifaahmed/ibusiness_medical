<?php

namespace App\Http\Resources\Admin\Contract\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminContractEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nameTranslations = $this->getTranslations('name');
        $descriptionTranslations = $this->getTranslations('description');

        return [
            'id' => $this->id,
            'name' => $nameTranslations,
            'slug' => $this->slug,
            'description' => $descriptionTranslations,
            'phones' => $this->phones,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'image' => $this->image,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
