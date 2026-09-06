<?php

namespace App\Http\Controllers\Admin\Facility\Location;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\LocateFacilityBranchRequest;
use App\Services\BranchGeocoder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Find on map with AI" button in the facility form's branch modal.
 * Called over axios, so it answers JSON: the coordinates and the Maps link go
 * into the open form for the admin to check and save, exactly like the
 * "Generate SEO with AI" button fills the SEO tab.
 */
class AdminFacilityBranchLocateController extends BaseController
{
    public function __construct(private readonly BranchGeocoder $geocoder) {}

    public function __invoke(LocateFacilityBranchRequest $request): JsonResponse
    {
        try {
            $location = $this->geocoder->locate($request->validated());
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are both the admin's to
            // act on, so surface the message instead of a bare 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($location['latitude'] === null) {
            return response()->json([
                'message' => 'The address could not be placed on the map. Add more detail — street, district, city — and try again.',
            ], 422);
        }

        return response()->json(['location' => $location]);
    }
}
