<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\OfferResource;
use App\Http\Resources\Guest\FacilityCollection;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PartnersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->input('facility_type_id'),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
        ];

        $with = ['facilityType', 'branches.governorate', 'branches.city', 'media', 'tags'];

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
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $locale = app()->getLocale();
                $normalized = $this->normalizeSearch($filters['search']);

                $words = collect(preg_split('/\s+/', $normalized))
                    ->map(fn ($w) => trim($w))
                    ->filter(fn ($w) => mb_strlen($w) > 1)
                    ->values();

                if ($words->isEmpty()) {
                    $words = collect([$normalized]);
                }

                $path = '$.'.$locale;
                $nameExpr = 'replace(replace(replace(replace('.
                    "json_unquote(json_extract(`name`, '{$path}')), ".
                    "'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";

                foreach ($words as $word) {
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
            ->latest()
            ->paginate($request->input('per_page', 12))
            ->withQueryString();

        $governorates = Governorate::all()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
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

        $facilityTypes = $facilityTypesQuery->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
        ]);

        /*
         * The cities a visitor can narrow to. Under a chosen governorate the
         * list is that governorate's hosting cities; without one it is every
         * city with something on the ground, so the pick stays meaningful
         * rather than shipping places nobody is listed in.
         */
        $citiesQuery = City::query();
        if ($governorateId !== null) {
            $citiesQuery->where('governorate_id', $governorateId);
        }

        $cities = $citiesQuery
            ->where(function ($q) {
                $q->whereHas('facilities')->orWhereHas('branches');
            })
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
            ]);

        $locale = App::getLocale();
        $facilityNames = Facility::select('name', 'slug')->get()->map(fn ($f) => [
            'slug' => $f->slug,
            'name' => is_string($f->name) ? $f->name : ($f->getTranslation('name', $locale) ?? ''),
        ]);

        // The offers carousel sits above the grid on every consumer of this
        // endpoint, so it ships in the same response: one request paints the
        // whole page instead of the grid arriving before the banner.
        $offers = OfferResource::collection(
            Offer::with(['offerable'])->orderByDesc('created_at')->get()
        );

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

    protected function normalizeSearch(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $term);
        $term = str_replace('ى', 'ي', $term);
        $term = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $term);

        return $term;
    }
}
