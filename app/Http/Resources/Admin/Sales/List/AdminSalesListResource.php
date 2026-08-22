<?php

namespace App\Http\Resources\Admin\Sales\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSalesListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'image' => $this->image,
            'facilities_count' => $this->facilities_count ?? 0,
            'facilities' => $this->facilities->map(fn ($facility) => [
                'id' => $facility->id,
                'name' => $facility->getTranslations('name'),
                'slug' => $facility->slug,
                'facility_type' => $facility->facilityType?->getTranslations('name'),
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
