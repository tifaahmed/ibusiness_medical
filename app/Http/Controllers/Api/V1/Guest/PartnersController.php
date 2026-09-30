<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\OfferResource;
use App\Http\Resources\Guest\FacilityCollection;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use App\Support\DirectorySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class PartnersController extends Controller
{
    /** Steps a visitor may pick "within X km" from — see `nearestBranchKm()`. */
    private const RADIUS_STEPS_KM = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

    public function __invoke(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->input('facility_type_id'),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
        ];

<<<<<<< HEAD
        /*
         * "Within X km": the browser's own coordinates plus a radius step.
         * Distance is measured to a facility's NEAREST branch — the same
         * place a card leads with when a governorate or city is chosen above
         * — computed once as a joined subquery rather than N+1 Haversine
         * calls. `lat`/`lng` alone (no radius) still asks for the distance,
         * for a "closest first" sort that reorders locally on the storefront
         * side rather than here — see `.ai/rules` on the Deilar repo for why
         * this endpoint otherwise forwards no sort of its own.
         */
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:radius_km'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:radius_km'],
            'radius_km' => ['nullable', 'integer', 'in:'.implode(',', self::RADIUS_STEPS_KM)],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $radiusKm = isset($validated['radius_km']) ? (int) $validated['radius_km'] : null;
        $hasPoint = $lat !== null && $lng !== null;

        $with = ['facilityType', 'branches.governorate', 'branches.city', 'media', 'tags'];
=======
        $with = ['facilityType', 'branches.governorate', 'branches.city', 'branches.area', 'media', 'tags'];
>>>>>>> 2904f9a523fe2f3c8c07d8b95c667119a1818cc0

        /*
         * A card leads with a branch, and the moment a visitor filters by
         * governorate or city is exactly when order matters: a facility whose
         * head office sits elsewhere would otherwise lead with a branch in the
         * wrong place. Matching branches come first; the rest keep their
         * natural order. A city implies its governorate, so with both chosen
         * the city match outranks the governorate-wide ones.
         */
        $governorateId = empty($filters['governorate_id']) ? null : (int) $filters['governorate_id'];
        $cityId = empty($filters['city_id']) ? null : (int) $filters['city_id'];

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

        $facilities = Facility::with($with)
            /*
             * Folded through `DirectorySearch` — the same helper the suggestion
             * endpoint matches with. The two have to agree letter for letter:
             * a visitor who picks "see all results" off a suggestion list has
             * to land on a grid holding the row they were looking at.
             */
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $nameExpr = DirectorySearch::translated('name', app()->getLocale());

                foreach (DirectorySearch::words($filters['search']) as $word) {
                    $q->where(function ($query) use ($word, $nameExpr) {
                        $query->whereRaw("{$nameExpr} like ?", ['%'.$word.'%'])
                            ->orWhere('slug', 'like', '%'.$word.'%');
                    });
                }
            })
            ->when(! empty($filters['facility_type_id']), fn ($q) => $q->where('facility_type_id', (int) $filters['facility_type_id']))
            ->when(! empty($filters['governorate_id']), function ($q) use ($filters) {
                $governorateId = (int) $filters['governorate_id'];
                $q->where(function ($query) use ($governorateId) {
                    $query->where('governorate_id', $governorateId)
                        ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId));
                });
            })
            ->when(! empty($filters['city_id']), function ($q) use ($filters) {
                $cityId = (int) $filters['city_id'];
                $q->where(function ($query) use ($cityId) {
                    $query->where('city_id', $cityId)
                        ->orWhereHas('branches', fn ($b) => $b->where('city_id', $cityId));
                });
            })
            ->when($hasPoint, function ($q) use ($lat, $lng, $radiusKm) {
                $q->leftJoinSub(
                    $this->nearestBranchKm($lat, $lng),
                    'nearest_branch',
                    fn ($join) => $join->on('facilities.id', '=', 'nearest_branch.facility_id'),
                )
                    ->select('facilities.*')
                    ->addSelect('nearest_branch.distance_km');

                if ($radiusKm !== null) {
                    $q->whereNotNull('nearest_branch.distance_km')
                        ->where('nearest_branch.distance_km', '<=', $radiusKm);
                }
            })
            ->latest()
            ->paginate($request->input('per_page', 12))
            ->withQueryString();

        /*
         * Only governorates an actual branch sits in — a facility's own
         * head-office address does not count here, unlike the grid filter
         * above. The two dropdowns are the whole reason a visitor opens
         * them: an entry that can only ever return the wrong sort of "here"
         * (a registered address rather than a place to walk into) is worse
         * than a shorter, honest list.
         */
        $governorateCounts = $this->facilityCountsByPlace('governorate_id');
        $governorates = Governorate::whereHas('branches')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'facility_count' => (int) ($governorateCounts[$g->id] ?? 0),
            ]);

        /*
         * Only the types a visitor would actually find under the place they
         * have narrowed to — offering "Pharmacy" where there is none is a
         * dropdown entry whose only outcome is an empty grid. A city is the
         * narrower of the two, so it decides when both are chosen.
         */
        $facilityTypesQuery = FacilityType::query();

        if ($governorateId !== null || $cityId !== null) {
            $ids = Facility::where(function ($q) use ($governorateId, $cityId) {
                if ($cityId !== null) {
                    $q->where('city_id', $cityId)
                        ->orWhereHas('branches', fn ($b) => $b->where('city_id', $cityId));

                    return;
                }

                $q->where('governorate_id', $governorateId)
                    ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId));
            })->distinct()->pluck('facility_type_id')->filter()->toArray();

            $facilityTypesQuery->when($ids, fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->whereRaw('1 = 0'));
        }

        $typeCounts = $this->facilityCountsByType($governorateId, $cityId);
        $facilityTypes = $facilityTypesQuery->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'facility_count' => (int) ($typeCounts[$t->id] ?? 0),
        ]);

        /*
         * The cities a visitor can narrow to. Under a chosen governorate the
         * list is that governorate's hosting cities; without one it is every
         * city an actual branch sits in — a facility's own head-office
         * address does not count here, same as the governorates above.
         */
        $citiesQuery = City::query();
        if ($governorateId !== null) {
            $citiesQuery->where('governorate_id', $governorateId);
        }

        $cityCounts = $this->facilityCountsByPlace('city_id');
        $cities = $citiesQuery
            ->whereHas('branches')
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'facility_count' => (int) ($cityCounts[$city->id] ?? 0),
            ]);

        $locale = App::getLocale();
        $facilityNames = Facility::select('name', 'slug')->get()->map(fn ($f) => [
            'slug' => $f->slug,
            'name' => is_string($f->name) ? $f->name : ($f->getTranslation('name', $locale) ?? ''),
        ]);

        // The offers carousel sits above the grid on every consumer of this
        // endpoint, so it ships in the same response: one request paints the
        // whole page instead of the grid arriving before the banner. It is
        // narrowed by the same filters as the grid, so a search or a picked
        // place hides an offer nowhere near it rather than a banner strip
        // that contradicts the results underneath.
        $offersQuery = Offer::query()->with(['offerable']);
        $this->applyOfferFilters($offersQuery, $filters, $locale);
        $offers = OfferResource::collection($offersQuery->orderByDesc('created_at')->get());

        return response()->json([
            'facilities' => (new FacilityCollection($facilities))->toArray($request),
            'filters' => $filters,
            'facility_types' => $facilityTypes,
            'governorates' => $governorates,
            'cities' => $cities,
            'facility_names' => $facilityNames,
            'offers' => $offers,
        ]);
    }

    /**
     * One row per facility that has at least one geocoded branch: the
     * great-circle distance (km) from `$lat`/`$lng` to its NEAREST branch,
     * via the standard Haversine formula. `LEAST`/`GREATEST` clamp the
     * `ACOS` argument to [-1, 1] — floating-point rounding can push a point
     * essentially on top of a branch a hair outside that range, which would
     * otherwise make `ACOS` return `NAN` for the exact case this query is
     * most likely to be asked about.
     */
    private function nearestBranchKm(float $lat, float $lng): \Illuminate\Database\Query\Builder
    {
        $distance = '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        ))))';

        return DB::table('facility_branches')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('facility_id')
            ->selectRaw('facility_id, MIN('.$distance.') as distance_km', [$lat, $lng, $lat]);
    }

    /**
     * Every governorate's (or city's) `facility_count` in ONE query, rather
     * than one query per dropdown row — the directory can list up to a few
     * hundred cities, and a query per row was the actual cost of this
     * endpoint (measured: ~280 of these across governorates, cities and
     * types, most of the request's total time).
     *
     * A facility touches a place through its own head office OR any branch,
     * the same either-counts rule the grid filters by — so this unions
     * "facility's own place" with "each branch's place" first. `UNION`
     * (not `UNION ALL`) already collapses an identical (facility, place)
     * pair coming from both sides; `COUNT(DISTINCT facility_id)` on the
     * outside is belt and braces for a facility with several branches in
     * the same place still counting once.
     *
     * @param  'governorate_id'|'city_id'  $column
     * @return Collection<int, int> facility_count keyed by the place's id
     */
    private function facilityCountsByPlace(string $column): Collection
    {
        $own = DB::table('facilities')
            ->select('id as facility_id', $column)
            ->whereNotNull($column);

        $branch = DB::table('facility_branches')
            ->select('facility_id', $column)
            ->whereNotNull($column);

        return DB::query()
            ->fromSub($own->union($branch), 'touches')
            ->select($column, DB::raw('COUNT(DISTINCT facility_id) as facility_count'))
            ->groupBy($column)
            ->pluck('facility_count', $column);
    }

    /**
     * Every facility type's `facility_count` in one query — narrowed to
     * whichever of governorate/city is currently chosen, the same "city
     * wins" rule that narrows which types are even listed. Unnarrowed, it is
     * every facility of that type; narrowed, "touches this place" is the
     * same own-or-branch rule {@see facilityCountsByPlace()} unions, here
     * AND'd onto the type filter and grouped by type instead of by place.
     *
     * @return Collection<int, int> facility_count keyed by facility_type_id
     */
    private function facilityCountsByType(?int $governorateId, ?int $cityId): Collection
    {
        return Facility::query()
            ->whereNotNull('facility_type_id')
            ->when(
                $cityId !== null,
                fn ($q) => $q->where(function ($qq) use ($cityId) {
                    $qq->where('city_id', $cityId)
                        ->orWhereHas('branches', fn ($b) => $b->where('city_id', $cityId));
                }),
            )
            ->when(
                $cityId === null && $governorateId !== null,
                fn ($q) => $q->where(function ($qq) use ($governorateId) {
                    $qq->where('governorate_id', $governorateId)
                        ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId));
                }),
            )
            ->select('facility_type_id', DB::raw('COUNT(*) as facility_count'))
            ->groupBy('facility_type_id')
            ->pluck('facility_count', 'facility_type_id');
    }

    /**
     * Narrow an offers query to whatever the grid itself is narrowed to.
     *
     * An offer's `offerable` is a `Facility` or a `FacilityBranch`, so each
     * filter has to be checked a different way for the two — the same
     * "itself, or a branch of it" shape the facility grid above uses for
     * governorate and city, extended to the facility type and the search
     * term.
     *
     * @param  array{search?: ?string, facility_type_id?: mixed, governorate_id?: mixed, city_id?: mixed}  $filters
     */
    private function applyOfferFilters(Builder $query, array $filters, string $locale): void
    {
        $words = empty($filters['search']) ? [] : DirectorySearch::words($filters['search']);
        $facilityTypeId = empty($filters['facility_type_id']) ? null : (int) $filters['facility_type_id'];
        $governorateId = empty($filters['governorate_id']) ? null : (int) $filters['governorate_id'];
        $cityId = empty($filters['city_id']) ? null : (int) $filters['city_id'];

        if ($words === [] && $facilityTypeId === null && $governorateId === null && $cityId === null) {
            return;
        }

        $query->whereHasMorph(
            'offerable',
            [Facility::class, FacilityBranch::class],
            function (Builder $query, string $type) use ($words, $facilityTypeId, $governorateId, $cityId, $locale) {
                $nameExpr = DirectorySearch::translated('name', $locale);

                foreach ($words as $word) {
                    $query->where(function (Builder $query) use ($word, $nameExpr) {
                        $query->whereRaw("{$nameExpr} like ?", ['%'.$word.'%'])
                            ->orWhere('slug', 'like', '%'.$word.'%');
                    });
                }

                $isFacility = $type === Facility::class;

                if ($facilityTypeId !== null) {
                    $isFacility
                        ? $query->where('facility_type_id', $facilityTypeId)
                        : $query->whereHas('facility', fn ($q) => $q->where('facility_type_id', $facilityTypeId));
                }

                if ($governorateId !== null) {
                    $query->where(function (Builder $query) use ($governorateId, $isFacility) {
                        $query->where('governorate_id', $governorateId);

                        $isFacility
                            ? $query->orWhereHas('branches', fn ($q) => $q->where('governorate_id', $governorateId))
                            : $query->orWhereHas('facility', fn ($q) => $q->where('governorate_id', $governorateId));
                    });
                }

                if ($cityId !== null) {
                    $query->where(function (Builder $query) use ($cityId, $isFacility) {
                        $query->where('city_id', $cityId);

                        $isFacility
                            ? $query->orWhereHas('branches', fn ($q) => $q->where('city_id', $cityId))
                            : $query->orWhereHas('facility', fn ($q) => $q->where('city_id', $cityId));
                    });
                }
            }
        );
    }
}
