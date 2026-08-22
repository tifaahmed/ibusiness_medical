<?php

namespace App\Http\Resources\Admin\Sales\Show;

use App\Http\Resources\Admin\Concerns\ResolvesTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSalesShowResource extends JsonResource
{
    use ResolvesTranslations;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->translationMap('name'),
            'image' => $this->image,
            'facilities_count' => $this->facilities_count ?? 0,
            'facilities' => $this->whenLoaded('facilities', fn () => $this->facilities->map(fn ($facility) => [
                'id' => $facility->id,
                'name' => $facility->getTranslations('name'),
                'slug' => $facility->slug,
                'facility_type' => $facility->facilityType?->getTranslations('name'),
            ])->values()),
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
