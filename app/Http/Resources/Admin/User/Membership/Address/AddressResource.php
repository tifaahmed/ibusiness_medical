<?php

namespace App\Http\Resources\Admin\User\Membership\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_id' => $this->membership_id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'address' => $this->address,
            'street' => $this->street,
            'governorate_id' => $this->governorate_id,
            'city_id' => $this->city_id,
            'governorate_label' => $this->whenLoaded('governorate', fn () => $this->governorate?->getTranslation('name', app()->getLocale()) ?: $this->governorate?->getTranslation('name', 'en')),
            'city_label' => $this->whenLoaded('city', fn () => $this->city?->getTranslation('name', app()->getLocale()) ?: $this->city?->getTranslation('name', 'en')),
            'building_number' => $this->building_number,
            'apartment_number' => $this->apartment_number,
            'floor_number' => $this->floor_number,
            'special_mark' => $this->special_mark,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
