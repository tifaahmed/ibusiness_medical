<?php

namespace App\Http\Resources\Admin\User\Membership\Edit;

use App\Http\Resources\Admin\User\Membership\Address\AddressResource;
use App\Http\Resources\Admin\User\Membership\FamilyMember\FamilyMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserMembershipEditResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'slug' => $this->slug,
            'avatar_url' => get_image_url($this->resource, 'avatar'),
            'membership' => $this->whenLoaded('memberships', function () use ($request) {
                // Get the first membership (ordered by active desc, then created_at desc in controller)
                $membership = $this->memberships->first();

                return $membership ? [
                    'id' => $membership->id,
                    'membership_number' => $membership->membership_number,
                    'national_id' => $membership->national_id,
                    'slug' => $membership->slug,
                    'registration_date' => $membership->registration_date?->format('Y-m-d'),
                    'expiration_date' => $membership->expiration_date?->format('Y-m-d'),
                    'registration_date_formatted' => $membership->registration_date?->format('Y-m-d'),
                    'expiration_date_formatted' => $membership->expiration_date?->format('Y-m-d'),
                    'is_active' => (bool) $membership->is_active,
                    'is_visible' => (bool) $membership->is_visible,
                    'is_paid' => (bool) $membership->is_paid,
                    'payment_type' => $membership->payment_type,
                    'completed_at' => $membership->completed_at?->format('Y-m-d H:i:s'),
                    'has_member_payments' => ($membership->member_payments_count ?? 0) > 0,
                    'member_payments' => $membership->relationLoaded('memberPayments')
                        ? $membership->memberPayments->map(fn ($mp) => [
                            'id' => $mp->id,
                            'amount' => (float) $mp->amount,
                            'type' => $mp->type,
                            'months_paid' => $mp->months_paid,
                            'from_date' => $mp->from_date?->format('Y-m-d'),
                            'to_date' => $mp->to_date?->format('Y-m-d'),
                            'notes' => $mp->notes,
                            'created_at' => $mp->created_at?->format('Y-m-d H:i:s'),
                        ])->values()->toArray()
                        : [],
                    'contract_image_url' => optional($membership->getMedia('contract')->first())->getUrl(),
                    'gallery_images' => $membership->getMedia('gallery')->map(fn ($m) => [
                        'id' => $m->id,
                        'url' => $m->getUrl(),
                        'name' => $m->name,
                    ])->values(),
                    'job_title' => $membership->getTranslations('job_title'),
                    'company_id' => $membership->company_id,
                    'partner_id' => $membership->partner_id,
                    'sales_id' => $membership->sales_id,
                    'governorate_id' => $membership->governorate_id,
                    'city_id' => $membership->city_id,
                    'family_members' => $membership->relationLoaded('familyMembers')
                        ? $membership->familyMembers->map(function ($familyMember) use ($request) {
                            return (new FamilyMemberResource($familyMember))->toArray($request);
                        })->toArray()
                        : [],
                    'addresses' => $membership->relationLoaded('addresses')
                        ? $membership->addresses->map(function ($address) use ($request) {
                            return (new AddressResource($address))->toArray($request);
                        })->toArray()
                        : [],
                    /* The primary (first) address, flat for the edit form's
                       Address section — the Addresses tab lists them all. */
                    'address' => $membership->relationLoaded('addresses') && $membership->addresses->isNotEmpty()
                        ? [
                            'id' => $membership->addresses->first()->id,
                            'type' => $membership->addresses->first()->type?->value,
                            'address' => $membership->addresses->first()->address,
                            'street' => $membership->addresses->first()->street,
                            'governorate_id' => $membership->addresses->first()->governorate_id,
                            'city_id' => $membership->addresses->first()->city_id,
                            'building_number' => $membership->addresses->first()->building_number,
                            'apartment_number' => $membership->addresses->first()->apartment_number,
                            'floor_number' => $membership->addresses->first()->floor_number,
                            'special_mark' => $membership->addresses->first()->special_mark,
                        ]
                        : null,
                    'created_at' => $membership->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $membership->updated_at?->format('Y-m-d H:i:s'),
                    'created_by' => $membership->created_by,
                    'creator' => $membership->relationLoaded('creator') && $membership->creator ? [
                        'id' => $membership->creator->id,
                        'name' => $membership->creator->name,
                        'email' => $membership->creator->email,
                    ] : null,
                ] : null;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
