<?php

namespace App\Http\Resources\Api\V1\Guest;

use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'phone' => $this->phone,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'discount_percentage' => $this->discount_percentage,
            'has_discount' => $this->hasDiscount(),
            'image' => $this->mobile_image,
            'thumbnail' => $this->mobile_thumbnail,
            'offerable_type' => $this->offerable_type,
            'offerable_name' => $this->whenLoaded('offerable', fn () => $this->offerable->name),
            // The partner's mark, so an offer card can be attributed without a
            // second lookup. Branches and other offerables may not carry one.
            'offerable_logo' => $this->whenLoaded(
                'offerable',
                fn () => $this->offerable?->logo ?? null
            ),
            'offerable_slug' => $this->whenLoaded(
                'offerable',
                fn () => $this->offerable?->slug ?? null
            ),
            // What the popup needs beyond attribution: the type of place, and
            // every branch it can be visited at — a branch offer resolves to
            // just the one it was raised on, a facility offer to all of them.
            'offerable_facility_type' => $this->whenLoaded(
                'offerable',
                fn () => $this->offerableFacilityTypeArray()
            ),
            'offerable_branches' => $this->whenLoaded(
                'offerable',
                fn () => $this->offerableBranches()->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'address' => $branch->address,
                    // Flat numbers, unchanged: the marketing site reads this.
                    'phone' => $branch->phoneNumbers(),
                    'phones' => $branch->phone,
                    'governorate' => $branch->relationLoaded('governorate') && $branch->governorate ? [
                        'id' => $branch->governorate->id,
                        'name' => $branch->governorate->name,
                    ] : null,
                    'city' => $branch->relationLoaded('city') && $branch->city ? [
                        'id' => $branch->city->id,
                        'name' => $branch->city->name,
                    ] : null,
                ])->values()
            ),
        ];
    }

    /**
     * The facility a branch offer's branch belongs to, or the facility
     * itself when the offer was raised directly on one.
     */
    private function offerableFacility(): ?Facility
    {
        $offerable = $this->offerable;

        return $offerable instanceof FacilityBranch ? $offerable->facility : $offerable;
    }

    /**
     * @return array{id: int, name: mixed, slug: string}|null
     */
    private function offerableFacilityTypeArray(): ?array
    {
        $facilityType = $this->offerableFacility()?->facilityType;

        return $facilityType ? [
            'id' => $facilityType->id,
            'name' => $facilityType->name,
            'slug' => $facilityType->slug,
        ] : null;
    }

    /**
     * Every branch the popup should list: the one branch an offer was raised
     * on, or all of a facility's branches when it was raised on the facility.
     *
     * @return Collection<int, FacilityBranch>
     */
    private function offerableBranches(): Collection
    {
        $offerable = $this->offerable;

        if ($offerable instanceof FacilityBranch) {
            return collect([$offerable]);
        }

        if ($offerable instanceof Facility) {
            return $offerable->branches ?? collect();
        }

        return collect();
    }
}
