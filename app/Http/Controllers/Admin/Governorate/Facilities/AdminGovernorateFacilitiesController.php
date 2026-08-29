<?php

namespace App\Http\Controllers\Admin\Governorate\Facilities;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the "Facilities" popup on the governorate list: every facility that
 * sits in this governorate, each with its branches, answered as JSON for the
 * dialog to render (with a link out to each facility's show page).
 */
class AdminGovernorateFacilitiesController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_GOVERNORATES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_GOVERNORATES;
    }

    public function __invoke(Request $request, string $governorate): JsonResponse
    {
        $governorate = Governorate::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->where('slug', $governorate)
            ->firstOrFail();

        $this->assertOwns($governorate);

        $locale = app()->getLocale();
        $governorateId = (int) $governorate->id;

        $facilities = Facility::query()
            ->with(['facilityType', 'city', 'branches.governorate', 'branches.city'])
            // Its own governorate is this one, or it has a branch located here.
            ->where(fn ($q) => $q
                ->where('governorate_id', $governorateId)
                ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId)))
            ->get()
            ->sortBy(fn ($facility) => mb_strtolower(
                $facility->getTranslation('name', $locale) ?: $facility->getTranslation('name', 'ar')
            ))
            ->values()
            ->map(function ($facility) use ($governorateId) {
                $facilityCity = $facility->city?->getTranslations('name');

                $branches = $facility->branches
                    ->map(function ($branch) use ($governorateId, $facilityCity) {
                        // A branch with no governorate saved is treated as sitting
                        // in the facility's governorate — only an explicitly
                        // different governorate counts as "other".
                        $otherGovernorate = $branch->governorate_id !== null
                            && (int) $branch->governorate_id !== $governorateId;

                        $name = $branch->getTranslations('name');

                        return [
                            'id' => $branch->id,
                            'name' => $name,
                            'address' => $branch->getTranslations('address'),
                            'governorate' => $branch->governorate?->getTranslations('name'),
                            'city' => $branch->city?->getTranslations('name') ?: $facilityCity,
                            'in_this_governorate' => ! $otherGovernorate,
                            'other_governorate' => $otherGovernorate,
                            '_sort' => mb_strtolower(
                                $branch->getTranslation('name', app()->getLocale())
                                    ?: (string) ($name['ar'] ?? '')
                            ),
                        ];
                    })
                    // Branches in this governorate first, then alphabetical.
                    ->sortBy(fn ($branch) => sprintf('%d %s', $branch['other_governorate'] ? 1 : 0, $branch['_sort']))
                    ->map(fn ($branch) => collect($branch)->except('_sort')->all())
                    ->values();

                return [
                    'id' => $facility->id,
                    'slug' => $facility->slug,
                    'name' => $facility->getTranslations('name'),
                    'facility_type' => $facility->facilityType?->getTranslations('name'),
                    'city' => $facilityCity,
                    'branches' => $branches,
                ];
            });

        return response()->json([
            'governorate' => ['name' => $governorate->getTranslations('name')],
            'facilities' => $facilities,
        ]);
    }
}
