<?php

namespace App\Http\Controllers\Admin\FacilityBranch\List;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;

/**
 * The border of one governorate as a GeoJSON geometry, for the branch map to
 * outline when it is filtered by governorate. `geometry` is null when no
 * border is stored (the map then simply draws none).
 *
 * Read-only reference data, behind the same permission as the map feed.
 */
class AdminFacilityBranchGovernorateBoundaryController extends BaseController
{
    public function __invoke(Governorate $governorate): JsonResponse
    {
        return response()->json([
            'id' => $governorate->id,
            'geometry' => $governorate->boundary,
        ]);
    }
}
