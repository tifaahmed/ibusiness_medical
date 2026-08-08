<?php

namespace App\Http\Resources\Admin\MembershipUsage\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMembershipUsageListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->whenLoaded('membership', fn() => $this->membership);
        $facility   = $this->whenLoaded('facility', fn() => $this->facility);
        $branch     = $this->whenLoaded('facilityBranch', fn() => $this->facilityBranch);
        $type       = $this->whenLoaded('facilityType', fn() => $this->facilityType);

        return [
            'id'                   => $this->id,
            'membership_id'        => $this->membership_id,
            'membership_number'    => $membership?->membership_number,
            'user_name'            => $membership?->user?->name,
            'facility_id'          => $this->facility_id,
            'facility_name'        => $facility?->getTranslation('name', app()->getLocale()) ?? $facility?->name,
            'facility_branch_id'   => $this->facility_branch_id,
            'facility_branch_name' => $branch?->getTranslation('name', app()->getLocale()),
            'facility_type_id'     => $this->facility_type_id,
            'facility_type_name'   => $type?->getTranslation('name', app()->getLocale()) ?? $type?->name,
            'amount'               => $this->amount,
            'description'          => $this->description,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
