<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every geocoded branch's raw point, paginated — the Deilar storefront's
 * "view all branches" map reads this once (cached client-side for a day) and
 * clusters the points itself in the browser as it is panned and zoomed,
 * rather than asking this endpoint again on every move.
 *
 * `ClusterFacilityBranches` / `FacilityBranchMapController` do the same
 * merging server-side instead, for a consumer that wants one round trip per
 * viewport rather than the whole network up front — the two are independent
 * ways of answering the same "too many pins" problem, and a consumer picks
 * whichever fits it.
 *
 * Public and key-less, like the rest of the guest API.
 */
class FacilityBranchLocationsController extends Controller
{
    /** Small enough that even a slow connection finishes a page quickly. */
    private const PER_PAGE = 300;

    private const MAX_PER_PAGE = 500;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:radius_km'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:radius_km'],
            'radius_km' => ['nullable', 'integer', 'in:10,20,30,40,50,60,70,80,90,100'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'facility_type_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $radiusKm = isset($validated['radius_km']) ? (int) $validated['radius_km'] : null;
        $hasPoint = $lat !== null && $lng !== null;

        $query = FacilityBranch::query()
            ->with('facility:id,slug,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($validated['governorate_id'] ?? null, fn ($q, $id) => $q->where('governorate_id', $id))
            ->when($validated['city_id'] ?? null, fn ($q, $id) => $q->where('city_id', $id))
            ->when(
                $validated['facility_type_id'] ?? null,
                fn ($q, $id) => $q->whereHas('facility', fn ($f) => $f->where('facility_type_id', $id)),
            );

        /*
         * Ordered nearest-first when the browser knows where it is, so the
         * storefront's progressive load paints the branches closest to the
         * visitor before the ones on the far side of the country. Without a
         * point, plain id order is at least stable across pages. `radius_km`
         * reuses the same distance expression to narrow the query itself —
         * the directory's own "within X km" filter (`PartnersController`)
         * has to match what this map shows for the same search.
         */
        if ($hasPoint) {
            $distance = '(6371 * ACOS(LEAST(1, GREATEST(-1,
                COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
            ))))';

            $query->select('facility_branches.*')
                ->selectRaw($distance.' as distance_km', [$lat, $lng, $lat])
                ->orderBy('distance_km');

            if ($radiusKm !== null) {
                $query->havingRaw('distance_km <= ?', [$radiusKm]);
            }
        } else {
            $query->orderBy('id');
        }

        $branches = $query->paginate(
            min((int) ($validated['per_page'] ?? self::PER_PAGE), self::MAX_PER_PAGE),
        );

        return response()->json([
            'data' => collect($branches->items())->map(fn (FacilityBranch $branch) => [
                'id' => $branch->id,
                'latitude' => (float) $branch->latitude,
                'longitude' => (float) $branch->longitude,
                'name' => $branch->name,
                'facility' => $branch->facility ? [
                    'slug' => $branch->facility->slug,
                    'name' => $branch->facility->name,
                ] : null,
            ])->values(),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'total' => $branches->total(),
            ],
        ])
            ->header('Cache-Control', $hasPoint ? 'no-store' : 'public, max-age=300');
    }
}
