<?php

namespace App\Http\Controllers\Admin\FacilityBranch\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the branch list's map view. Paginated the same way the list itself
 * is, so a facility with thousands of branches does not hand the browser
 * thousands of pins and rows in one response.
 *
 * Read-only, so it sits behind the same permission as the list itself
 * (`view facility branches` is enough), and shares that controller's
 * creator-scoping and filters so a map pin never shows a branch the list
 * itself would not.
 */
class AdminFacilityBranchMapDataController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $facilityId = $request->input('facility_id');

        $branches = FacilityBranch::query()
            ->with(['facility:id,name', 'governorate:id,name', 'city:id,name'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name->'.app()->getLocale(), 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('facility', function ($q) use ($search) {
                            $q->where('name->'.app()->getLocale(), 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId))
            ->when($request->input('governorate_id'), fn ($q, $governorateId) => $q->where('governorate_id', $governorateId))
            ->when($request->input('city_id'), fn ($q, $cityId) => $q->where('city_id', $cityId))
            ->when($request->input('facility_type_id'), function ($q, $facilityTypeId) {
                $q->whereHas('facility', fn ($q2) => $q2->where('facility_type_id', $facilityTypeId));
            })
            ->when($request->boolean('no_governorate'), fn ($q) => $q->whereNull('governorate_id'))
            ->when($request->boolean('no_city'), fn ($q) => $q->whereNull('city_id'))
            // Every branch on this map has coordinates, so "no GPS" leaves it empty — by design.
            ->when($request->boolean('no_gps'), fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => collect($branches->items())->map(fn (FacilityBranch $branch) => [
                'id' => $branch->id,
                'slug' => $branch->slug,
                'name' => $branch->getTranslations('name'),
                'facility' => $branch->facility ? [
                    'id' => $branch->facility->id,
                    'name' => $branch->facility->getTranslations('name'),
                ] : null,
                'governorate' => $branch->governorate?->getTranslations('name'),
                'city' => $branch->city?->getTranslations('name'),
                'address' => $branch->getTranslations('address'),
                'latitude' => (float) $branch->latitude,
                'longitude' => (float) $branch->longitude,
                'google_location_url' => $branch->google_location_url,
            ])->values(),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
                'from' => $branches->firstItem(),
                'to' => $branches->lastItem(),
            ],
        ]);
    }
}
