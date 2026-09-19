<?php

namespace App\Http\Controllers\Admin\FacilityBranch\List;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use Illuminate\Http\JsonResponse;

/**
 * The border of one city as a GeoJSON geometry, for the branch map to outline
 * when it is filtered by city. `geometry` is null when no border is stored
 * (the map then simply draws none).
 *
 * Read-only reference data, behind the same permission as the map feed.
 */
class AdminFacilityBranchCityBoundaryController extends BaseController
{
    public function __invoke(City $city): JsonResponse
    {
        return response()->json([
            'id' => $city->id,
            'geometry' => $city->boundary,
        ]);
    }
}
