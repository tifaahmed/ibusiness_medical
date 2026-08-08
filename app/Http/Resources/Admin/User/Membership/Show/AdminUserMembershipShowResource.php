<?php

namespace App\Http\Resources\Admin\User\Membership\Show;

use App\Http\Resources\Admin\User\Membership\FamilyMember\FamilyMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserMembershipShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'slug' => $this->slug,
            'avatar_url' => get_image_url($this->resource, 'avatar'),
            'memberships' => $this->whenLoaded('memberships', function () use ($request, $locale) {
                return $this->memberships->map(function ($membership) use ($request, $locale) {
                    return [
                        'id' => $membership->id,
                        'membership_number' => $membership->membership_number,
                        'slug' => $membership->slug,
                        'registration_date' => $membership->registration_date?->format('Y-m-d H:i:s'),
                        'expiration_date' => $membership->expiration_date?->format('Y-m-d H:i:s'),
                        'is_active' => (bool) $membership->is_active,
                        'is_visible' => (bool) $membership->is_visible,
                        'completed_at' => $membership->completed_at?->format('Y-m-d H:i:s'),
                        'is_paid' => (bool) $membership->is_paid,
                        'payment_type' => $membership->payment_type,
                        'job_title' => $membership->getTranslation('job_title', $locale)
                            ?: $membership->getTranslation('job_title', 'ar')
                            ?: $membership->getTranslation('job_title', 'en'),
                        'job_title_ar' => $membership->getTranslation('job_title', 'ar'),
                        'job_title_en' => $membership->getTranslation('job_title', 'en'),
                        'company_id' => $membership->company_id,
                        'company_name' => $membership->relationLoaded('company') && $membership->company
                            ? ($membership->company->getTranslation('name', $locale)
                                ?: $membership->company->getTranslation('name', 'ar')
                                ?: $membership->company->getTranslation('name', 'en'))
                            : null,
                        'partner_id' => $membership->partner_id,
                        'partner_name' => $membership->relationLoaded('partner') && $membership->partner
                            ? $membership->partner->title
                            : null,
                        'partner_image' => $membership->relationLoaded('partner') && $membership->partner
                            ? $membership->partner->image
                            : null,
                        'sales_id' => $membership->sales_id,
                        'sale_name' => $membership->relationLoaded('sales') && $membership->sales
                            ? ($membership->sales->getTranslation('name', $locale)
                                ?: $membership->sales->getTranslation('name', 'ar')
                                ?: $membership->sales->getTranslation('name', 'en'))
                            : null,
                        'governorate_id' => $membership->governorate_id,
                        'governorate_name' => $membership->relationLoaded('governorate') && $membership->governorate
                            ? ($membership->governorate->getTranslation('name', $locale)
                                ?: $membership->governorate->getTranslation('name', 'ar')
                                ?: $membership->governorate->getTranslation('name', 'en'))
                            : null,
                        'city_id' => $membership->city_id,
                        'city_name' => $membership->relationLoaded('city') && $membership->city
                            ? ($membership->city->getTranslation('name', $locale)
                                ?: $membership->city->getTranslation('name', 'ar')
                                ?: $membership->city->getTranslation('name', 'en'))
                            : null,
                        'contract_image_url' => optional($membership->getMedia('contract')->first())->getUrl(),
                        'gallery_images' => $membership->getMedia('gallery')->map(fn ($m) => [
                            'id' => $m->id,
                            'url' => $m->getUrl(),
                            'name' => $m->name,
                        ])->values(),
                        'family_members' => $membership->relationLoaded('familyMembers')
                            ? $membership->familyMembers->map(function ($familyMember) use ($request) {
                                return (new FamilyMemberResource($familyMember))->toArray($request);
                            })->toArray()
                            : [],
                        'member_payments' => $membership->relationLoaded('memberPayments')
                            ? $membership->memberPayments->map(fn ($mp) => [
                                'id' => $mp->id,
                                'amount' => (float) $mp->amount,
                                'months_paid' => $mp->months_paid,
                                'from_date' => $mp->from_date?->format('Y-m-d'),
                                'to_date' => $mp->to_date?->format('Y-m-d'),
                                'notes' => $mp->notes,
                                'created_at' => $mp->created_at?->format('Y-m-d H:i:s'),
                            ])->toArray()
                            : [],
                        'created_at' => $membership->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $membership->updated_at?->format('Y-m-d H:i:s'),
                        'created_by' => $membership->created_by,
                        'creator' => $membership->relationLoaded('creator') && $membership->creator ? [
                            'id' => $membership->creator->id,
                            'name' => $membership->creator->name,
                            'email' => $membership->creator->email,
                        ] : null,
                        'card_layouts' => $membership->relationLoaded('cardLayouts')
                            ? $membership->cardLayouts->map(fn ($cl) => [
                                'id' => $cl->id,
                                'mode' => $cl->mode,
                                'partner_x' => $cl->partner_x,
                                'partner_y' => $cl->partner_y,
                                'partner_scale' => $cl->partner_scale,
                                'photo_x' => $cl->photo_x,
                                'photo_y' => $cl->photo_y,
                                'photo_scale' => $cl->photo_scale,
                                'name_x' => $cl->name_x,
                                'name_y' => $cl->name_y,
                                'name_scale' => $cl->name_scale,
                                'name_color' => $cl->name_color,
                                'fields_x' => $cl->fields_x,
                                'fields_y' => $cl->fields_y,
                                'fields_scale' => $cl->fields_scale,
                                'fields_color' => $cl->fields_color,
                                'qr_x' => $cl->qr_x,
                                'qr_y' => $cl->qr_y,
                                'qr_scale' => $cl->qr_scale,
                                'image_url' => $cl->generated_image_path
                                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($cl->generated_image_path)
                                    : null,
                            ])->values()->toArray()
                            : [],
                    ];
                })->toArray();
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
