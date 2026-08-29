<?php

namespace App\Http\Resources\Guest;

use App\Http\Resources\Admin\User\Membership\FamilyMember\FamilyMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
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
            'membership_number' => $this->membership_number,
            'slug' => $this->slug,
            'registration_date' => $this->registration_date?->format('Y-m-d H:i:s'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d H:i:s'),
            'registration_date_formatted' => $this->registration_date?->format('F j, Y'),
            'expiration_date_formatted' => $this->expiration_date?->format('F j, Y'),
            'is_active' => $this->is_active ?? false,
            'job_title' => $this->getTranslation('job_title', app()->getLocale()) ?: $this->getTranslation('job_title', 'ar') ?: $this->getTranslation('job_title', 'en'),
            'company_name' => $this->company_id
                ? ($this->company?->getTranslation('name', app()->getLocale()) ?: $this->company?->getTranslation('name', 'ar') ?: $this->company?->getTranslation('name', 'en'))
                : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'avatar_url' => get_image_url($this->user, 'avatar'),
                ];
            }),
            'family_members' => $this->whenLoaded('familyMembers', function () use ($request) {
                return $this->familyMembers->map(function ($familyMember) use ($request) {
                    return (new FamilyMemberResource($familyMember))->toArray($request);
                })->toArray();
            }, []),
            'total_paid' => $this->whenLoaded('usages', fn () => (float) $this->usages->sum('amount'), 0),
            'usages' => $this->whenLoaded('usages', function () {
                $locale = app()->getLocale();
                $other = $locale === 'ar' ? 'en' : 'ar';

                return $this->usages->map(fn ($u) => [
                    'id' => $u->id,
                    'facility_name' => $u->facility?->getTranslation('name', $locale) ?: $u->facility?->getTranslation('name', $other),
                    'facility_branch_name' => $u->facilityBranch
                        ? ($u->facilityBranch->getTranslation('name', $locale) ?: $u->facilityBranch->getTranslation('name', $other))
                        : null,
                    'facility_type_name' => $u->facilityType?->getTranslation('name', $locale) ?: $u->facilityType?->getTranslation('name', $other),
                    'amount' => $u->amount,
                    'description' => $u->description,
                    'gallery' => $u->gallery,
                    'created_at' => $u->created_at?->format('Y-m-d H:i:s'),
                ])->toArray();
            }, []),
            'partner' => $this->whenLoaded('partner', function () {
                if (! $this->partner) {
                    return null;
                }

                return [
                    'id' => $this->partner->id,
                    'title' => $this->partner->title,
                    'image' => $this->partner->image,
                    'card_x' => $this->partner->card_x,
                    'card_y' => $this->partner->card_y,
                    'card_scale' => $this->partner->card_scale,
                ];
            }),
            'card_layouts' => $this->whenLoaded('cardLayouts', function () {
                return $this->cardLayouts->map(function ($layout) {
                    $cardTemplate = null;
                    if ($layout->relationLoaded('cardTemplate') && $layout->cardTemplate) {
                        $cardTemplate = [
                            'id' => $layout->cardTemplate->id,
                            'name' => $layout->cardTemplate->getTranslation('name', app()->getLocale())
                                ?: $layout->cardTemplate->getTranslation('name', 'ar')
                                ?: $layout->cardTemplate->getTranslation('name', 'en'),
                            'slug' => $layout->cardTemplate->slug,
                            'status' => $layout->cardTemplate->status?->value,
                            'card_empty_url' => $layout->cardTemplate->card_empty_url,
                            'sample_card_url' => $layout->cardTemplate->sample_card_url,
                            'layout' => $layout->cardTemplate->effectiveLayout(),
                            'sample_data' => $layout->cardTemplate->sample_data,
                            'hidden_fields' => $layout->cardTemplate->hidden_fields,
                        ];
                    }

                    return [
                        'id' => $layout->id,
                        'mode' => $layout->mode,
                        'partner_id' => $layout->partner_id,
                        'card_template_id' => $layout->card_template_id,
                        'card_template' => $cardTemplate,
                        'layout' => $layout->layout,
                        'field_values' => $layout->field_values,
                        'partner_x' => $layout->partner_x ? (float) $layout->partner_x : null,
                        'partner_y' => $layout->partner_y ? (float) $layout->partner_y : null,
                        'partner_scale' => $layout->partner_scale ? (float) $layout->partner_scale : null,
                        'photo_x' => $layout->photo_x ? (float) $layout->photo_x : null,
                        'photo_y' => $layout->photo_y ? (float) $layout->photo_y : null,
                        'photo_scale' => $layout->photo_scale ? (float) $layout->photo_scale : null,
                        'name_x' => $layout->name_x ? (float) $layout->name_x : null,
                        'name_y' => $layout->name_y ? (float) $layout->name_y : null,
                        'name_scale' => $layout->name_scale ? (float) $layout->name_scale : null,
                        'name_color' => $layout->name_color,
                        'fields_x' => $layout->fields_x ? (float) $layout->fields_x : null,
                        'fields_y' => $layout->fields_y ? (float) $layout->fields_y : null,
                        'fields_scale' => $layout->fields_scale ? (float) $layout->fields_scale : null,
                        'fields_color' => $layout->fields_color,
                        'qr_x' => $layout->qr_x ? (float) $layout->qr_x : null,
                        'qr_y' => $layout->qr_y ? (float) $layout->qr_y : null,
                        'qr_scale' => $layout->qr_scale ? (float) $layout->qr_scale : null,
                        'generated_image_path' => $layout->generated_image_path,
                        'generated_image_url' => $layout->generated_image_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($layout->generated_image_path)
                            : null,
                    ];
                })->toArray();
            }, []),
            'card_url' => $this->when($this->relationLoaded('cardLayouts'), function () {
                $cardUrl = null;
                if ($this->slug) {
                    $cardUrl = url('/api/v1/memberships/'.$this->slug.'/card?mode=full');
                }

                return $cardUrl;
            }),
        ];
    }
}
