<?php

namespace App\Http\Resources\Admin\User\Membership\FamilyMember;

use App\Enums\FamilyMember\ApprovalStatusEnum;
use App\Enums\FamilyMember\RelationshipEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A relative, drawn from its `memberships` row (type `relative`) and its user.
 * The JSON keys are the ones the old family table served, so admin pages and
 * clients keep reading the same shape; `id` is now the relative's membership id.
 */
class FamilyMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $relationship = RelationshipEnum::tryFrom((string) $this->relationship);
        $dob = $this->user?->date_of_birth;

        return [
            'id' => $this->id,
            'membership_id' => $this->parent_membership_id,
            'membership_number' => $this->membership_number,
            'name' => $this->user?->name,
            'relationship' => $this->relationship,
            'relationship_label' => $relationship?->label() ?? $this->relationship,
            'date_of_birth' => $dob?->format('Y-m-d'),
            'date_of_birth_formatted' => $dob?->format('Y-m-d'),
            'phone' => $this->user?->phone,
            'email' => $this->user?->email,
            'photo_url' => get_image_url($this->resource, 'photo', '/images/defaults/avatar.png'),
            'has_photo' => $this->resource->hasMedia('photo'),
            'is_active' => $this->is_active ?? true,
            'national_id' => $this->national_id,
            'approval_status' => $this->approval_status ?: ApprovalStatusEnum::APPROVED->value,
            'added_by' => $this->added_by,
            'rejection_reason' => $this->rejection_reason,
            'has_id_front' => $this->resource->hasMedia('national_id_front'),
            'has_id_back' => $this->resource->hasMedia('national_id_back'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
