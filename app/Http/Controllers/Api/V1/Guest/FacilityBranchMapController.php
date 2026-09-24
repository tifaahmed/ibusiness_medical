<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Facilities\ClusterFacilityBranches;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every branch on the map, at once — merged by proximity so a directory with
 * thousands of branches never hands the browser thousands of pins in one go.
 *
 * Public and key-less like the rest of the guest API. Read by the Deilar
 * storefront's "view all branches" map.
 */
class FacilityBranchMapController extends Controller
{
    public function __construct(private ClusterFacilityBranches $cluster) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zoom' => ['nullable', 'integer', 'min:1', 'max:19'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'facility_type_id' => ['nullable', 'integer', 'exists:facility_types,id'],
        ]);

        try {
            $clusters = $this->cluster->handle(
                zoom: (int) ($validated['zoom'] ?? 6),
                governorateId: $validated['governorate_id'] ?? null,
                cityId: $validated['city_id'] ?? null,
                facilityTypeId: $validated['facility_type_id'] ?? null,
            );
        } catch (Throwable $exception) {
            Log::error('Branch map clustering failed.', [
                'zoom' => $validated['zoom'] ?? null,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['clusters' => []], 500);
        }

        return response()->json(['clusters' => $clusters])
            /*
             * Cacheable and short-lived, same reasoning as the search box:
             * the answer is the same for everybody looking at the same
             * filters and zoom level, and two minutes is long enough to
             * absorb a burst of pans/zooms without a newly added branch
             * staying invisible for long.
             */
            ->header('Cache-Control', 'public, max-age=120');
    }
}
