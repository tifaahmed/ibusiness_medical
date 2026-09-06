<?php

namespace App\Http\Resources\Admin\Facility\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One branch as the facility form needs it.
 *
 * Shared by the facility edit payload and the inline branch save endpoint so a
 * branch saved from the modal comes back in exactly the shape the form already
 * holds for the branches it was given on page load.
 *
 * @mixin \App\Models\FacilityBranch
 */
class AdminFacilityEditBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'address' => $this->getTranslations('address'),
            'phone' => $this->phone,
            'governorate_id' => $this->governorate_id,
            'city_id' => $this->city_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_location_url' => $this->google_location_url,
            // Read-only on the form: who first added the branch, and when.
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toDateString(),
            'slug' => $this->slug,
        ];
    }
}
