<?php

namespace App\Http\Controllers\Admin\Facility\Place;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * The areas of one city, names only, for the branch form's optional "Area"
 * picker. Lightweight on purpose (no borders): 5,000+ areas are far too many to
 * ship with the page, so the form asks for the chosen city's few dozen when the
 * city changes. Read-only; behind the same permissions as the branch form.
 */
class AdminFacilityBranchCityAreasController extends BaseController
{
    public function __invoke(City $city): JsonResponse
    {
        try {
            $areas = $city->areas()
                ->select(['id', 'pcode', 'name'])
                ->orderBy('id')
                ->get()
                ->map(fn ($area) => [
                    'id' => $area->id,
                    'pcode' => $area->pcode,
                    'name' => $area->getTranslations('name'),
                ])
                ->values();
        } catch (\Throwable $e) {
            Log::error('Branch form: the areas of a city could not be loaded', ['city_id' => $city->id, 'exception' => $e]);

            return response()->json(['message' => 'The areas could not be loaded.'], 500);
        }

        return response()->json(['city_id' => $city->id, 'areas' => $areas]);
    }
}
