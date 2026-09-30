<?php

namespace App\Http\Controllers\Admin\City;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Area;
use App\Models\City;
use App\Support\GeoJson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One city: its name, its areas and (fetched separately by the page, because
 * the GeoJSON is large) the map. Plain arrays, not a JsonResource — see
 * AdminCityListController.
 */
class AdminCityShowController extends BaseController
{
    public function __invoke(Request $request, City $city): Response
    {
        $city->load('governorate:id,slug,name')->loadCount(['branches', 'facilities', 'areas']);

        $user = $request->user();
        $canManageAreas = (bool) $user?->can('manage areas');
        $canViewAreas = $canManageAreas || (bool) $user?->can('view areas');

        // Areas have their own permission: a city page for someone without it shows none.
        $areas = ! $canViewAreas ? collect() : $city->areas()
            ->select(['id', 'pcode', 'name'])
            ->selectRaw('boundary is not null as has_border')
            ->orderBy('id')
            ->get()
            ->map(fn ($area) => [
                'id' => $area->id,
                'pcode' => $area->pcode,
                'name' => $area->getTranslations('name'),
                'has_border' => (bool) $area->has_border,
            ])
            ->values();

        return Inertia::render('Admin/City/Show', [
            'insideArea' => $canViewAreas && $areas->isEmpty() ? $this->areaHolding($city) : null,
            'city' => [
                'id' => $city->id,
                'name' => $city->getTranslations('name'),
                'is_unmarked' => $city->isUnmarked(),
                'branches_count' => $city->branches_count,
                'areas_count' => $city->areas_count,
                'facilities_count' => $city->facilities_count,
                'governorate' => [
                    'id' => $city->governorate_id,
                    'slug' => $city->governorate?->slug,
                    'name' => $city->governorate?->getTranslations('name'),
                ],
            ],
            'areas' => $areas,
            'canManage' => (bool) $user?->can('manage cities'),
            'canViewAreas' => $canViewAreas,
            'canManageAreas' => $canManageAreas,
        ]);
    }

    /**
     * A city that owns no areas usually sits INSIDE one: the census units are
     * often larger than the neighbourhoods the city borders were drawn around
     * (Miami is inside the area of Nasseriya). Names that area, and the city it
     * is filed under, so the empty list explains itself and links somewhere.
     */
    private function areaHolding(City $city): ?array
    {
        $centre = GeoJson::centroid($city->boundary);
        if ($centre === null) {
            return null;
        }

        $area = Area::query()
            ->with('city:id,name')
            ->where('governorate_id', $city->governorate_id)
            ->get(['id', 'city_id', 'pcode', 'name', 'boundary'])
            ->first(fn (Area $candidate) => GeoJson::contains($candidate->boundary, $centre[0], $centre[1]));

        return $area === null ? null : [
            'id' => $area->id,
            'pcode' => $area->pcode,
            'name' => $area->getTranslations('name'),
            'city' => ['id' => $area->city_id, 'name' => $area->city?->getTranslations('name')],
        ];
    }
}
