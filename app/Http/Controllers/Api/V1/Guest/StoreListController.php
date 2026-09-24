<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Guest\StoreCollection;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * The public stores directory, read by the Deilar storefront — mirrors
 * `PartnersController` but trimmed for a domain with no head-office address
 * of its own (only branches have one) and no type/tags/offers-morph relation.
 */
class StoreListController extends Controller
{
    /** Steps a visitor may pick "within X km" from — see `nearestBranchKm()`. */
    private const RADIUS_STEPS_KM = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

    public function __invoke(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
        ];

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:radius_km'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:radius_km'],
            'radius_km' => ['nullable', 'integer', 'in:'.implode(',', self::RADIUS_STEPS_KM)],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $radiusKm = isset($validated['radius_km']) ? (int) $validated['radius_km'] : null;
        $hasPoint = $lat !== null && $lng !== null;

        $governorateId = empty($filters['governorate_id']) ? null : (int) $filters['governorate_id'];
        $cityId = empty($filters['city_id']) ? null : (int) $filters['city_id'];

        $with = ['branches.governorate', 'branches.city', 'media'];

        /*
         * The moment a visitor filters by governorate or city, matching
         * branches lead — same reasoning as `PartnersController`'s own
         * branch reorder, except a store has no head-office address to
         * compete with: every branch is already "a place", so there is no
         * "wrong place" case to guard against here.
         */
        if ($governorateId !== null || $cityId !== null) {
            $with['branches'] = function ($query) use ($governorateId, $cityId) {
                if ($cityId !== null && $governorateId !== null) {
                    $query->orderByRaw(
                        'CASE WHEN city_id = ? THEN 0 WHEN governorate_id = ? THEN 1 ELSE 2 END',
                        [$cityId, $governorateId],
                    )->orderBy('id');

                    return;
                }

                if ($cityId !== null) {
                    $query->orderByRaw('CASE WHEN city_id = ? THEN 0 ELSE 1 END', [$cityId])
                        ->orderBy('id');

                    return;
                }

                $query->orderByRaw('CASE WHEN governorate_id = ? THEN 0 ELSE 1 END', [$governorateId])
                    ->orderBy('id');
            };
        }

        $stores = Store::with($with)
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $titleExpr = \App\Support\DirectorySearch::translated('title', app()->getLocale());

                foreach (\App\Support\DirectorySearch::words($filters['search']) as $word) {
                    $q->where(function ($query) use ($word, $titleExpr) {
                        $query->whereRaw("{$titleExpr} like ?", ['%'.$word.'%'])
                            ->orWhere('slug', 'like', '%'.$word.'%');
                    });
                }
            })
            /*
             * Store carries no governorate/city of its own — only its
             * branches do — so, unlike Facility, there is no "itself, or a
             * branch" pair to check: it is branches alone.
             */
            ->when(! empty($filters['governorate_id']), function ($q) use ($filters) {
                $q->whereHas('branches', fn ($b) => $b->where('governorate_id', (int) $filters['governorate_id']));
            })
            ->when(! empty($filters['city_id']), function ($q) use ($filters) {
                $q->whereHas('branches', fn ($b) => $b->where('city_id', (int) $filters['city_id']));
            })
            ->when($hasPoint, function ($q) use ($lat, $lng, $radiusKm) {
                $q->leftJoinSub(
                    $this->nearestBranchKm($lat, $lng),
                    'nearest_branch',
                    fn ($join) => $join->on('stores.id', '=', 'nearest_branch.store_id'),
                )
                    ->select('stores.*')
                    ->addSelect('nearest_branch.distance_km');

                if ($radiusKm !== null) {
                    $q->whereNotNull('nearest_branch.distance_km')
                        ->where('nearest_branch.distance_km', '<=', $radiusKm);
                }
            })
            ->latest()
            ->paginate($request->input('per_page', 12))
            ->withQueryString();

        $governorates = Governorate::whereHas('storeBranches')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'store_count' => $this->storeCountForGovernorate($g->id),
            ]);

        $citiesQuery = City::query();
        if ($governorateId !== null) {
            $citiesQuery->where('governorate_id', $governorateId);
        }

        $cities = $citiesQuery
            ->whereHas('storeBranches')
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'store_count' => $this->storeCountForCity($city->id),
            ]);

        $locale = App::getLocale();
        $storeNames = Store::select('title', 'slug')->get()->map(fn ($s) => [
            'slug' => $s->slug,
            'name' => is_string($s->title) ? $s->title : ($s->getTranslation('title', $locale) ?? ''),
        ]);

        return response()->json([
            'stores' => (new StoreCollection($stores))->toArray($request),
            'filters' => $filters,
            'governorates' => $governorates,
            'cities' => $cities,
            'store_names' => $storeNames,
        ]);
    }

    /**
     * One row per store that has at least one geocoded branch: the
     * great-circle distance (km) to its NEAREST branch — identical formula to
     * `PartnersController::nearestBranchKm()`, over `store_branches`.
     */
    private function nearestBranchKm(float $lat, float $lng): \Illuminate\Database\Query\Builder
    {
        $distance = '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        ))))';

        return DB::table('store_branches')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('store_id')
            ->selectRaw('store_id, MIN('.$distance.') as distance_km', [$lat, $lng, $lat]);
    }

    private function storeCountForGovernorate(int $governorateId): int
    {
        return Store::whereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId))->count();
    }

    private function storeCountForCity(int $cityId): int
    {
        return Store::whereHas('branches', fn ($b) => $b->where('city_id', $cityId))->count();
    }
}
