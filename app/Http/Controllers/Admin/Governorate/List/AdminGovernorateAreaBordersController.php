<?php

namespace App\Http\Controllers\Admin\Governorate\List;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * The areas of one city with their borders as GeoJSON geometry, for the
 * governorate map view to draw once a city is picked. Fetched per city, not per
 * governorate: 5,670 borders at once is several MB the map has no use for.
 *
 * Read-only reference data, behind the same permission as the list.
 */
class AdminGovernorateAreaBordersController extends BaseController
{
    public function __invoke(City $city): JsonResponse
    {
        try {
            $areas = $city->areas()
                ->select(['id', 'city_id', 'pcode', 'name', 'boundary'])
                ->orderBy('id')
                ->get()
                ->map(fn ($area) => [
                    'id' => $area->id,
                    'pcode' => $area->pcode,
                    'name' => $area->getTranslations('name'),
                    'geometry' => $area->boundary,
                ])
                ->values();
        } catch (\Throwable $e) {
            Log::error('Area borders could not be loaded', ['city_id' => $city->id, 'exception' => $e]);

            return response()->json(['message' => 'Area borders could not be loaded.'], 500);
        }

        return response()->json([
            'city_id' => $city->id,
            'governorate_id' => $city->governorate_id,
            'areas' => $areas,
        ]);
    }
}
