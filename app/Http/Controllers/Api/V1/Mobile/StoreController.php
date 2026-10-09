<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\Lookups;
use App\Http\Controllers\Api\V1\Mobile\Concerns\NearestBranch;
use App\Http\Controllers\Api\V1\Mobile\Concerns\RespondsCompactly;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreBranch;
use App\Models\StoreCategory;
use App\Support\DirectorySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The mobile app's stores API — see `FacilityController` for the ground rules.
 */
class StoreController extends Controller
{
    use Lookups;
    use NearestBranch;
    use RespondsCompactly;

    private const PER_PAGE = 12;

    private const BRANCHES_PER_PAGE = 10;

    /** Store categories that hold at least one store. */
    public function categories(Request $request): JsonResponse
    {
        $data = Cache::remember('mobile:store-categories:'.app()->getLocale(), 600, fn () => StoreCategory::query()
            ->withCount('stores')
            ->get(['id', 'name'])
            ->filter(fn ($c) => $c->stores_count > 0)
            ->sortByDesc('stores_count')
            ->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->name, 'count' => (int) $c->stores_count])
            ->values()
            ->all());

        return $this->respond($request, ['data' => $data], 600);
    }

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
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

        $query = Store::query()
            ->select(['stores.id', 'stores.slug', 'stores.title', 'stores.offer_percent_from', 'stores.offer_percent_to'])
            ->with([
                'media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo']),
                'categories:id,name',
            ])
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->when(! empty($v['q']), function ($q) use ($v) {
                $title = DirectorySearch::translated('title', app()->getLocale());

                foreach (DirectorySearch::words($v['q']) as $word) {
                    $q->where(fn ($w) => $w->whereRaw("{$title} like ?", ['%'.$word.'%'])->orWhere('slug', 'like', '%'.$word.'%'));
                }
            })
            ->when(! empty($v['category_id']), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('store_categories.id', (int) $v['category_id'])))
            ->when($governorateId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId)))
            ->when($cityId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('city_id', $cityId)));

        if ($hasPoint) {
            $query->leftJoinSub(
                $this->nearestBranchKm('store_branches', 'store_id', $lat, $lng),
                'nearest_branch',
                fn ($join) => $join->on('stores.id', '=', 'nearest_branch.store_id'),
            )->addSelect('nearest_branch.distance_km');

            if ($radius !== null) {
                $query->where('nearest_branch.distance_km', '<=', $radius);
            }

            $query->orderByRaw('nearest_branch.distance_km is null')
                ->orderBy('nearest_branch.distance_km')
                ->orderBy('stores.id');
        } else {
            $query->orderByDesc('stores.id');
        }

        $page = $query->simplePaginate($v['per_page'] ?? self::PER_PAGE)->withQueryString();

        $branches = StoreBranch::query()
            ->whereIn('store_id', $page->pluck('id'))
            ->get(['id', 'store_id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'address', 'phone'])
            ->groupBy('store_id');

        $governorates = $this->governorateNames();
        $cities = $this->cityNames();

        $data = $page->getCollection()->map(function (Store $s) use ($branches, $governorates, $cities, $lat, $lng, $governorateId, $cityId) {
            $branch = $this->leadBranch($branches->get($s->id, collect()), $lat, $lng, $governorateId, $cityId);

            return [
                'id' => $s->id,
                'slug' => $s->slug,
                'title' => (string) $s->title,
                'logo' => $s->mobile_logo ?: $s->logo,
                'offer_from' => $s->offer_percent_from !== null ? (float) $s->offer_percent_from : null,
                'offer_to' => $s->offer_percent_to !== null ? (float) $s->offer_percent_to : null,
                'categories' => $s->categories->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->name])->values()->all(),
                'products_count' => (int) $s->products_count,
                'branches_count' => $branches->get($s->id, collect())->count(),
                'distance_km' => isset($s->distance_km) ? round((float) $s->distance_km, 1) : null,
                'branch' => $branch ? $this->branchCard($branch, $governorates, $cities) : null,
            ];
        })->values()->all();

        return $this->respond($request, ['data' => $data, 'has_more' => $page->hasMorePages()], $hasPoint ? 30 : 120);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        $store->load([
            'media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo', 'header']),
            'categories:id,name',
            'galleries',
        ]);

        $locale = app()->getLocale();
        $coupons = collect($store->coupons ?? [])
            ->filter(fn ($c) => empty($c['expires_at']) || Carbon::parse($c['expires_at'])->endOfDay()->isFuture())
            ->map(fn ($c) => [
                'code' => (string) ($c['code'] ?? ''),
                'title' => is_array($c['title'] ?? null) ? ($c['title'][$locale] ?? collect($c['title'])->filter()->first()) : null,
            ])
            ->values()
            ->all();

        $gallery = collect($store->gallery)->where('type', 'image')->pluck('url')->take(12)->values()->all();

        $payload = [
            'id' => $store->id,
            'slug' => $store->slug,
            'title' => (string) $store->title,
            'short_description' => $store->short_description ? (string) $store->short_description : null,
            'description' => $this->plainText($store->description),
            'logo' => $store->mobile_logo ?: $store->logo,
            'cover' => $store->header ?: null,
            'gallery' => $gallery,
            'offer_from' => $store->offer_percent_from !== null ? (float) $store->offer_percent_from : null,
            'offer_to' => $store->offer_percent_to !== null ? (float) $store->offer_percent_to : null,
            'online_only' => (bool) $store->online_only,
            'categories' => $store->categories->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->name])->values()->all(),
            'websites' => $store->websites ?? [],
            'social_links' => collect($store->social_links ?? [])
                ->map(fn ($l) => ['platform' => $l['platform'] ?? null, 'url' => $l['url'] ?? null])
                ->filter(fn ($l) => $l['platform'] && $l['url'])
                ->values()
                ->all(),
            'coupons' => $coupons,
            'ships' => (bool) $store->supports_shipping,
            'ships_everywhere' => (bool) $store->ships_everywhere,
            'youtube_link' => $store->youtube_link ?: null,
            /* Columns added by a later migration; `getAttribute` reads null instead of failing where it has not run yet. */
            'app_store_url' => $store->getAttribute('app_store_url') ?: null,
            'google_play_url' => $store->getAttribute('google_play_url') ?: null,
        ] + $this->branchPage($request, $store->id, 1, min(40, max(1, (int) $request->query('per_page', self::BRANCHES_PER_PAGE))));

        return $this->respond($request, $payload, $request->filled('lat') ? 30 : 300);
    }

    public function branches(Request $request, Store $store): JsonResponse
    {
        $v = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:40']]);

        return $this->respond(
            $request,
            $this->branchPage($request, $store->id, (int) ($v['page'] ?? 1), (int) ($v['per_page'] ?? self::BRANCHES_PER_PAGE)),
            $request->filled('lat') ? 30 : 300,
        );
    }

    /** @return array{branches: list<array<string, mixed>>, has_more: bool} */
    private function branchPage(Request $request, int $storeId, int $page, int $perPage): array
    {
        $v = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
        ]);

        $query = StoreBranch::query()
            ->where('store_id', $storeId)
            ->select(['id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'name', 'address', 'phone', 'google_location_url'])
            ->when(! empty($v['governorate_id']), fn ($q) => $q->where('governorate_id', (int) $v['governorate_id']))
            ->when(! empty($v['city_id']), fn ($q) => $q->where('city_id', (int) $v['city_id']));

        if (isset($v['lat'], $v['lng'])) {
            $query->selectRaw(self::distanceSql().' as distance_km', [(float) $v['lat'], (float) $v['lng'], (float) $v['lat']])
                ->orderByRaw('distance_km is null')->orderBy('distance_km');
        }

        $result = $query->orderBy('id')->simplePaginate($perPage, ['*'], 'page', $page);
        $governorates = $this->governorateNames();
        $cities = $this->cityNames();

        return [
            'branches' => $result->getCollection()->map(fn (StoreBranch $b) => $this->branchCard($b, $governorates, $cities, true))->values()->all(),
            'has_more' => $result->hasMorePages(),
        ];
    }

    /**
     * @param  array<int, string>  $governorates
     * @param  array<int, string>  $cities
     * @return array<string, mixed>
     */
    private function branchCard(StoreBranch $b, array $governorates, array $cities, bool $detailed = false): array
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
