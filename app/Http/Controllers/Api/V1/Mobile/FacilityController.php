<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\Lookups;
use App\Http\Controllers\Api\V1\Mobile\Concerns\NearestBranch;
use App\Http\Controllers\Api\V1\Mobile\Concerns\RespondsCompactly;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Support\DirectorySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The mobile app's facilities API.
 *
 * Every payload here is built for one screen and carries nothing else: no
 * filter dropdowns, no list of every facility name, no offers, no SEO fields.
 * Lists use `simplePaginate` (no `COUNT(*)`), select only the columns a card
 * shows, and load only the media collections a card draws.
 */
class FacilityController extends Controller
{
    use Lookups;
    use NearestBranch;
    use RespondsCompactly;

    private const PER_PAGE = 12;

    private const BRANCHES_PER_PAGE = 10;

    /** Facility types that actually have facilities, with how many. */
    public function types(Request $request): JsonResponse
    {
        $types = Cache::remember('mobile:facility-type-counts:'.app()->getLocale(), 600, function () {
            $counts = Facility::query()
                ->whereNotNull('facility_type_id')
                ->selectRaw('facility_type_id, COUNT(*) as total')
                ->groupBy('facility_type_id')
                ->pluck('total', 'facility_type_id');

            return collect($this->facilityTypeMap())
                ->filter(fn ($type, $id) => ($counts[$id] ?? 0) > 0)
                ->map(fn ($type, $id) => $type + ['count' => (int) $counts[$id]])
                ->sortByDesc('count')
                ->values()
                ->all();
        });

        return $this->respond($request, ['data' => $types], 600);
    }

    /** Governorates that have at least one facility branch — id and name only. */
    public function governorates(Request $request): JsonResponse
    {
        $data = Cache::remember('mobile:facility-governorates:'.app()->getLocale(), 3600, function () {
            $ids = FacilityBranch::query()->whereNotNull('governorate_id')->distinct()->pluck('governorate_id')->all();

            return collect($this->governorateNames())
                ->only($ids)
                ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        });

        return $this->respond($request, ['data' => $data], 3600);
    }

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'integer'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:radius_km,lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:radius_km,lat'],
            'radius_km' => ['nullable', 'integer', 'in:'.implode(',', self::RADIUS_STEPS_KM)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:40'],
        ]);

        $lat = isset($v['lat']) ? (float) $v['lat'] : null;
        $lng = isset($v['lng']) ? (float) $v['lng'] : null;
        $radius = isset($v['radius_km']) ? (int) $v['radius_km'] : null;
        $governorateId = ! empty($v['governorate_id']) ? (int) $v['governorate_id'] : null;
        $cityId = ! empty($v['city_id']) ? (int) $v['city_id'] : null;
        $hasPoint = $lat !== null && $lng !== null;

        $query = Facility::query()
            ->select(['facilities.id', 'facilities.slug', 'facilities.name', 'facilities.facility_type_id', 'facilities.discount_percent'])
            ->with(['media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo'])])
            ->when(! empty($v['q']), function ($q) use ($v) {
                $name = DirectorySearch::translated('name', app()->getLocale());

                foreach (DirectorySearch::words($v['q']) as $word) {
                    $q->where(fn ($w) => $w->whereRaw("{$name} like ?", ['%'.$word.'%'])->orWhere('slug', 'like', '%'.$word.'%'));
                }
            })
            ->when(! empty($v['type']), fn ($q) => $q->where('facilities.facility_type_id', (int) $v['type']))
            ->when($governorateId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId)))
            ->when($cityId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('city_id', $cityId)));

        if ($hasPoint) {
            $query->leftJoinSub(
                $this->nearestBranchKm('facility_branches', 'facility_id', $lat, $lng),
                'nearest_branch',
                fn ($join) => $join->on('facilities.id', '=', 'nearest_branch.facility_id'),
            )->addSelect('nearest_branch.distance_km');

            if ($radius !== null) {
                $query->where('nearest_branch.distance_km', '<=', $radius);
            }

            $query->orderByRaw('nearest_branch.distance_km is null')
                ->orderBy('nearest_branch.distance_km')
                ->orderBy('facilities.id');
        } else {
            $query->orderByDesc('facilities.id');
        }

        $page = $query->simplePaginate($v['per_page'] ?? self::PER_PAGE)->withQueryString();

        // One query for every card's branches, only the columns a card shows.
        $branches = FacilityBranch::query()
            ->whereIn('facility_id', $page->pluck('id'))
            ->get(['id', 'facility_id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'address', 'phone'])
            ->groupBy('facility_id');

        $types = $this->facilityTypeMap();
        $governorates = $this->governorateNames();
        $cities = $this->cityNames();

        $data = $page->getCollection()->map(function (Facility $f) use ($branches, $types, $governorates, $cities, $lat, $lng, $governorateId, $cityId) {
            $branch = $this->leadBranch($branches->get($f->id, collect()), $lat, $lng, $governorateId, $cityId);

            return [
                'id' => $f->id,
                'slug' => $f->slug,
                'name' => (string) $f->name,
                'logo' => $f->mobile_logo ?: $f->logo,
                'discount' => $f->discount_percent !== null ? (float) $f->discount_percent : null,
                'type' => isset($types[$f->facility_type_id]) ? ['id' => $f->facility_type_id, 'name' => $types[$f->facility_type_id]['name']] : null,
                'distance_km' => isset($f->distance_km) ? round((float) $f->distance_km, 1) : null,
                'branch' => $branch ? $this->branchCard($branch, $governorates, $cities) : null,
            ];
        })->values()->all();

        return $this->respond($request, ['data' => $data, 'has_more' => $page->hasMorePages()], $hasPoint ? 30 : 120);
    }

    public function show(Request $request, Facility $facility): JsonResponse
    {
        $facility->load([
            'media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo', 'image', 'mobile_image']),
            'tags:id,name,color',
        ]);

        $types = $this->facilityTypeMap();

        $payload = [
            'id' => $facility->id,
            'slug' => $facility->slug,
            'name' => (string) $facility->name,
            'description' => $this->plainText($facility->description),
            'logo' => $facility->mobile_logo ?: $facility->logo,
            'image' => $facility->mobile_image ?: $facility->image,
            'discount' => $facility->discount_percent !== null ? (float) $facility->discount_percent : null,
            'type' => isset($types[$facility->facility_type_id]) ? ['id' => $facility->facility_type_id, 'name' => $types[$facility->facility_type_id]['name']] : null,
            'tags' => $facility->tags->map(fn ($t) => ['name' => (string) $t->name, 'color' => $t->color])->values()->all(),
        ] + $this->branchPage($request, $facility->id, 1, min(40, max(1, (int) $request->query('per_page', self::BRANCHES_PER_PAGE))));

        return $this->respond($request, $payload, $request->filled('lat') ? 30 : 300);
    }

    /**
     * The nearest geocoded branches as bare map pins: id, position and one
     * label. 300 of them come to ~20 KB, where the web directory's pin list
     * (branch name + facility slug + name + paging totals per pin) is ~96 KB.
     * No `COUNT(*)` either: `has_more` tells the map the list was capped.
     */
    public function pins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'in:'.implode(',', self::RADIUS_STEPS_KM)],
            'facility_type_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($v['limit'] ?? 300);
        $bindings = [(float) $v['lat'], (float) $v['lng'], (float) $v['lat']];

        $query = FacilityBranch::query()
            ->select(['id', 'facility_id', 'latitude', 'longitude'])
            ->selectRaw(self::distanceSql().' as distance_km', $bindings)
            ->with('facility:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when(! empty($v['facility_type_id']), fn ($q) => $q->whereHas('facility', fn ($f) => $f->where('facility_type_id', (int) $v['facility_type_id'])))
            ->when(isset($v['radius_km']), fn ($q) => $q->havingRaw('distance_km <= ?', [(int) $v['radius_km']]))
            ->orderBy('distance_km')
            ->limit($limit + 1);

        $rows = $query->get();

        return $this->respond($request, [
            'data' => $rows->take($limit)->map(fn (FacilityBranch $b) => [
                'id' => $b->id,
                'lat' => round((float) $b->latitude, 5),
                'lng' => round((float) $b->longitude, 5),
                'name' => (string) ($b->facility?->name ?? ''),
            ])->values()->all(),
            'has_more' => $rows->count() > $limit,
        ], 120);
    }

    /** One map pin's popup: the branch's place plus the little of its facility a card shows. */
    public function branch(Request $request, int $branch): JsonResponse
    {
        $model = FacilityBranch::query()
            ->select(['id', 'facility_id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'address', 'phone'])
            ->findOrFail($branch);

        $facility = Facility::query()
            ->select(['id', 'slug', 'name', 'facility_type_id', 'discount_percent'])
            ->with(['media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo'])])
            ->findOrFail($model->facility_id);

        $types = $this->facilityTypeMap();

        return $this->respond($request, [
            'id' => $facility->id,
            'slug' => $facility->slug,
            'name' => (string) $facility->name,
            'logo' => $facility->mobile_logo ?: $facility->logo,
            'discount' => $facility->discount_percent !== null ? (float) $facility->discount_percent : null,
            'type' => isset($types[$facility->facility_type_id]) ? ['id' => $facility->facility_type_id, 'name' => $types[$facility->facility_type_id]['name']] : null,
            'distance_km' => null,
            'branch' => $this->branchCard($model, $this->governorateNames(), $this->cityNames()),
        ], 300);
    }

    public function branches(Request $request, Facility $facility): JsonResponse
    {
        $v = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:40']]);

        return $this->respond(
            $request,
            $this->branchPage($request, $facility->id, (int) ($v['page'] ?? 1), (int) ($v['per_page'] ?? self::BRANCHES_PER_PAGE)),
            $request->filled('lat') ? 30 : 300,
        );
    }

    /**
     * A page of one facility's branches — nearest first when the app sends its
     * position, in id order otherwise. Optional `branch_q` narrows by name or
     * address; `governorate_id` / `city_id` narrow by place.
     *
     * @return array{branches: list<array<string, mixed>>, has_more: bool}
     */
    private function branchPage(Request $request, int $facilityId, int $page, int $perPage): array
    {
        $v = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'branch_q' => ['nullable', 'string', 'max:100'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
        ]);

        $locale = app()->getLocale();
        $search = trim((string) ($v['branch_q'] ?? ''));

        $query = FacilityBranch::query()
            ->where('facility_id', $facilityId)
            ->select(['id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'name', 'address', 'phone', 'google_location_url'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name->'.$locale, 'like', '%'.$search.'%')
                ->orWhere('address->'.$locale, 'like', '%'.$search.'%')))
            ->when(! empty($v['governorate_id']), fn ($q) => $q->where('governorate_id', (int) $v['governorate_id']))
            ->when(! empty($v['city_id']), fn ($q) => $q->where('city_id', (int) $v['city_id']));

        if (isset($v['lat'], $v['lng'])) {
            $query->selectRaw(self::distanceSql().' as distance_km', [(float) $v['lat'], (float) $v['lng'], (float) $v['lat']])
                ->orderByRaw('distance_km is null')->orderBy('distance_km');
        }

        $query->orderBy('id');

        $result = $query->simplePaginate($perPage, ['*'], 'page', $page);
        $governorates = $this->governorateNames();
        $cities = $this->cityNames();

        return [
            'branches' => $result->getCollection()->map(fn (FacilityBranch $b) => $this->branchCard($b, $governorates, $cities, true))->values()->all(),
            'has_more' => $result->hasMorePages(),
        ];
    }

    /**
     * @param  array<int, string>  $governorates
     * @param  array<int, string>  $cities
     * @return array<string, mixed>
     */
    private function branchCard(FacilityBranch $b, array $governorates, array $cities, bool $detailed = false): array
    {
        $phones = $this->primaryPhones($b->phone ?? []);

        $card = [
            'governorate' => $governorates[$b->governorate_id] ?? null,
            'city' => $cities[$b->city_id] ?? null,
            'address' => $b->address ? (string) $b->address : null,
            'lat' => $b->latitude !== null ? (float) $b->latitude : null,
            'lng' => $b->longitude !== null ? (float) $b->longitude : null,
            'phone' => $phones['phone'],
            'whatsapp' => $phones['whatsapp'],
        ];

        if (! $detailed) {
            return $card;
        }

        return ['id' => $b->id, 'name' => $b->name ? (string) $b->name : null]
            + $card
            + [
                'distance_km' => isset($b->distance_km) ? round((float) $b->distance_km, 1) : null,
                // The app builds a map link from lat/lng; the stored URL is only a fallback.
                'maps_url' => $b->latitude === null ? $b->google_location_url : null,
            ];
    }
}
