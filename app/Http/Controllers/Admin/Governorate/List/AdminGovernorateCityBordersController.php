<?php

namespace App\Http\Controllers\Admin\Governorate\List;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;

/**
 * The cities of one governorate (and its own border) with their borders as GeoJSON geometry, for the
 * map view of the governorate list to draw when a governorate is selected.
 * `geometry` is null for a city with no stored border (it stays in the list,
 * it is just not drawn).
 *
 * Read-only reference data, behind the same permission as the list.
 */
class AdminGovernorateCityBordersController extends BaseController
{
    public function __invoke(Governorate $governorate): JsonResponse
    {
        $cities = $governorate->cities()
            ->select(['id', 'governorate_id', 'slug', 'name', 'boundary'])
            ->orderBy('id')
            ->get()
            ->map(fn ($city) => [
                'id' => $city->id,
                'slug' => $city->slug,
                'name' => $city->getTranslations('name'),
                'is_unmarked' => $city->isUnmarked(),
                'geometry' => $city->boundary,
            ])
            ->values();

        return response()->json([
            'governorate_id' => $governorate->id,
            // The border editor on the edit page draws the governorate's own outline too.
            'governorate_geometry' => $governorate->boundary,
            'cities' => $cities,
        ]);
    }
}
