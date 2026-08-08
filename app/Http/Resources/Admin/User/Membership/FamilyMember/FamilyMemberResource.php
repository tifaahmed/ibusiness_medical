<?php

namespace App\Http\Resources\Admin\User\Membership\FamilyMember;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyMemberResource extends JsonResource
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
            'name' => $this->name,
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationship->label(),
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'date_of_birth_formatted' => $this->date_of_birth?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'photo_url' => get_image_url($this->resource, 'photo', '/images/defaults/avatar.png'),
            'has_photo' => $this->resource->hasMedia('photo'),
            'is_active' => $this->is_active ?? true,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

