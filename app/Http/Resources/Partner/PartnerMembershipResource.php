<?php

namespace App\Http\Resources\Partner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A membership as another property sees it.
 *
 * Deliberately narrower than `Guest\MembershipResource`: partners get the card
 * summary and the family on it, and nothing about money — no usages, no
 * payments, no card layout geometry. Adding a field here publishes it to every
 * partner holding the key, so add only what one of them has asked for.
 *
 * @mixin \App\Models\Membership
 */
class PartnerMembershipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $fallback = $locale === 'ar' ? 'en' : 'ar';

        return [
            'membership_number' => $this->membership_number,
            'is_active' => (bool) $this->is_active,
            'is_expired' => $this->expiration_date?->isPast() ?? false,
            'registration_date' => $this->registration_date?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'job_title' => $this->getTranslation('job_title', $locale)
                ?: $this->getTranslation('job_title', $fallback)
                ?: null,
            'company_name' => $this->company
                ? ($this->company->getTranslation('name', $locale) ?: $this->company->getTranslation('name', $fallback))
                : null,
            'member' => [
                'name' => $this->user?->name,
                'avatar_url' => $this->absoluteImageUrl($this->user, 'avatar'),
            ],
            'family_members' => $this->whenLoaded('familyMembers', fn () => $this->familyMembers
                ->map(fn ($familyMember) => [
                    'name' => $familyMember->name,
                    'relationship' => $familyMember->relationship?->value,
                    'relationship_label' => $familyMember->relationship?->label(),
                    'date_of_birth' => $familyMember->date_of_birth?->toDateString(),
                    'photo_url' => $this->absoluteImageUrl($familyMember, 'photo'),
                ])
                ->values()
                ->all(), []),
        ];
    }

    /**
     * Resolve a media URL that survives leaving this application.
     *
     * `get_image_url()` strips `APP_URL` off and hands back a root-relative
     * path, which is right for our own Inertia pages and useless to a caller on
     * another domain — the browser would resolve it against *their* host.
     */
    private function absoluteImageUrl(?object $model, string $collection): ?string
    {
        if (! $model instanceof \Spatie\MediaLibrary\HasMedia || ! $model->hasMedia($collection)) {
            return null;
        }

        $path = get_image_url($model, $collection);

        return str_starts_with($path, 'http') ? $path : url($path);
    }
}
