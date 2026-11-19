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

    /**
     * The governorates and cities that hold at least one store branch, so a client shows only the area buttons that lead somewhere
     * (an empty governorate or city is never offered). Ids only: names come from `/locations`.
     */
    public function areas(Request $request): JsonResponse
    {
        $data = Cache::remember('mobile:store-areas', 300, function () {
            $rows = StoreBranch::query()->whereHas('store')->whereNotNull('governorate_id')->get(['governorate_id', 'city_id']);

            return $rows->groupBy('governorate_id')->map(fn ($branches, $governorateId) => [
                'id' => (int) $governorateId,
                'cities' => $branches->pluck('city_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->all(),
            ])->values()->all();
        });

        return $this->respond($request, ['data' => $data], 300);
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
            'sort' => ['nullable', 'in:nearest,discount,visits,newest,none'],
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
            ->when(! empty($v['q']), fn ($q) => $this->matchingTyped($q, (string) $v['q']))
            ->when(! empty($v['category_id']), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('store_categories.id', (int) $v['category_id'])))
            ->when($governorateId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId)))
            ->when($cityId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('city_id', $cityId)));

        $byDiscount = ($v['sort'] ?? null) === 'discount';
        $byVisits = ($v['sort'] ?? null) === 'visits';

        if ($hasPoint) {
            $query->leftJoinSub(
                $this->nearestBranchKm('store_branches', 'store_id', $lat, $lng),
                'nearest_branch',
                fn ($join) => $join->on('stores.id', '=', 'nearest_branch.store_id'),
            )->addSelect('nearest_branch.distance_km');

            if ($radius !== null) {
                $query->where('nearest_branch.distance_km', '<=', $radius);
            }

            if ($byDiscount) {
                $query->orderByRaw('stores.offer_percent_to is null')->orderByDesc('stores.offer_percent_to');
            }

            if ($byVisits) {
                $query->orderByDesc('stores.visits_count');
            }

            if (($v['sort'] ?? null) === 'newest') {
                $query->orderByDesc('stores.id');
            }

            // `none` = no ordering chosen: the distance is still returned for display, but it does not rank the list.
            if (($v['sort'] ?? null) === 'none') {
                $query->orderByDesc('stores.id');
            } else {
                $query->orderByRaw('nearest_branch.distance_km is null')
                    ->orderBy('nearest_branch.distance_km')
                    ->orderBy('stores.id');
            }
        } else {
            if ($byDiscount) {
                $query->orderByRaw('stores.offer_percent_to is null')->orderByDesc('stores.offer_percent_to');
            }

            if ($byVisits) {
                $query->orderByDesc('stores.visits_count');
                // Equal counts (all 0 at first): a shuffle that stays the same all day, so paging never repeats a row.
                $query->orderByRaw('md5(concat(stores.id, curdate()))');
            }

            if (($v['sort'] ?? null) === 'newest') {
                $query->orderByDesc('stores.id');
            }

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
                'logo' => \App\Support\MobileImage::pick($s->mobile_logo, $s->logo),
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

    /**
     * Light pins for the stores map: one per geocoded store branch (id, position, store title), nearest first. Same shape and
     * limits as the facilities map (`FacilityController::pins`); the card of a tapped pin comes from `pin()`.
     */
    public function pins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'in:'.implode(',', self::RADIUS_STEPS_KM)],
            'category_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:3000'],
        ]);

        $limit = (int) ($v['limit'] ?? 300);
        $title = DirectorySearch::translated('title', app()->getLocale());

        $rows = StoreBranch::query()
            ->select(['id', 'store_id', 'latitude', 'longitude'])
            ->selectRaw(self::distanceSql().' as distance_km', [(float) $v['lat'], (float) $v['lng'], (float) $v['lat']])
            ->with('store:id,title')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when(! empty($v['category_id']), fn ($q) => $q->whereHas('store.categories', fn ($c) => $c->where('store_categories.id', (int) $v['category_id'])))
            ->when(! empty($v['q']), function ($q) use ($v) {
                $q->whereHas('store', fn ($s) => $this->matchingTyped($s, (string) $v['q']));
            })
            ->when(isset($v['radius_km']), fn ($q) => $q->havingRaw('distance_km <= ?', [(int) $v['radius_km']]))
            ->orderBy('distance_km')
            ->limit($limit + 1)
            ->get();

        return $this->respond($request, [
            'data' => $rows->take($limit)->map(fn (StoreBranch $b) => [
                'id' => $b->id,
                'lat' => round((float) $b->latitude, 5),
                'lng' => round((float) $b->longitude, 5),
                'name' => (string) ($b->store?->title ?? ''),
            ])->values()->all(),
            'has_more' => $rows->count() > $limit,
        ], 120);
    }

    /** The popup of one store-map pin: the store (logo, offer, categories) and this branch's place and phones. */
    public function pin(Request $request, int $branch): JsonResponse
    {
        $b = StoreBranch::query()
            ->select(['id', 'store_id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'name', 'address', 'area', 'phone', 'google_location_url'])
            ->findOrFail($branch);

        $store = Store::query()
            ->select(['id', 'slug', 'title', 'offer_percent_from', 'offer_percent_to'])
            ->with(['media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo']), 'categories:id,name'])
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->findOrFail($b->store_id);

        return $this->respond($request, [
            'id' => $store->id,
            'slug' => $store->slug,
            'title' => (string) $store->title,
            'logo' => \App\Support\MobileImage::pick($store->mobile_logo, $store->logo),
            'offer_from' => $store->offer_percent_from !== null ? (float) $store->offer_percent_from : null,
            'offer_to' => $store->offer_percent_to !== null ? (float) $store->offer_percent_to : null,
            'categories' => $store->categories->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->name])->values()->all(),
            'products_count' => (int) $store->products_count,
            'branch' => $this->branchCard($b, $this->governorateNames(), $this->cityNames(), true),
        ], 300);
    }

    /**
     * «Did you mean»: the stores whose names are CLOSEST to what was typed, best first. It does not need the exact name: a missing
     * or extra letter, a different spelling of an Arabic letter, or only the start of a word still finds it. The store list is short
     * (a few dozen to a few hundred rows), so every title is scored in PHP and the whole answer is cached for ten minutes.
     */
    public function suggest(Request $request): JsonResponse
    {
        $v = $request->validate(['q' => ['required', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:15']]);
        $query = DirectorySearch::normalise($v['q']);
        $words = array_values(array_filter(preg_split('/\s+/u', $query) ?: [], fn ($w) => mb_strlen($w) >= 1));
        $limit = (int) ($v['limit'] ?? 8);
        $locale = app()->getLocale();

        $stores = Cache::remember('mobile:store-suggest-index:'.$locale, 600, function () {
            $both = fn ($model, string $attr): array => collect(['ar', 'en'])
                ->map(fn ($l) => DirectorySearch::normalise(strip_tags((string) $model->getTranslation($attr, $l, false))))
                ->filter()->unique()->values()->all();

            return Store::query()
                ->with(['media' => fn ($q) => $q->whereIn('collection_name', ['logo', 'mobile_logo']), 'categories:id,name', 'branches.city:id,name', 'branches.governorate:id,name'])
                ->get()
                ->map(function (Store $s) use ($both) {
                    $places = $s->branches->flatMap(fn ($b) => array_merge($b->city ? $both($b->city, 'name') : [], $b->governorate ? $both($b->governorate, 'name') : []));

                    return [
                        'id' => $s->id,
                        'slug' => $s->slug,
                        'title' => (string) $s->title,
                        'logo' => \App\Support\MobileImage::pick($s->mobile_logo, $s->logo),
                        'category' => $s->categories->first()?->name ? (string) $s->categories->first()->name : null,
                        // [weight, text]: the name counts fully; a city, governorate or kind of store a little less; the address and the free text less still.
                        'fields' => collect()
                            ->merge(array_map(fn ($t) => [1.0, $t], array_merge($both($s, 'title'), [DirectorySearch::normalise(str_replace('-', ' ', (string) $s->slug))])))
                            ->merge($s->categories->flatMap(fn ($c) => array_map(fn ($t) => [0.92, $t], $both($c, 'name'))))
                            ->merge($places->unique()->map(fn ($t) => [0.92, $t]))
                            ->merge($s->branches->flatMap(fn ($b) => array_map(fn ($t) => [0.8, $t], array_merge($both($b, 'address'), $both($b, 'name')))))
                            ->merge(array_map(fn ($t) => [0.75, $t], array_merge($both($s, 'short_description'), $both($s, 'meta_keywords'), array_map(fn ($d) => mb_substr($d, 0, 600), $both($s, 'description')))))
                            ->filter(fn ($f) => $f[1] !== '')->values()->all(),
                    ];
                })->all();
        });

        $scored = [];

        foreach ($stores as $store) {
            $best = 0.0;

            foreach ($store['fields'] as [$weight, $text]) {
                $best = max($best, $weight * self::closeness($query, $words, $text));
            }

            if ($best >= 0.6) {
                $scored[] = ['score' => $best, 'row' => ['id' => $store['id'], 'slug' => $store['slug'], 'title' => $store['title'], 'logo' => $store['logo'], 'category' => $store['category']]];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $this->respond($request, ['data' => array_map(fn ($r) => $r['row'], array_slice($scored, 0, $limit))], 300);
    }

    /** 0..1: how close the typed text is to a name (1 = it is inside the name; otherwise word by word, tolerating typos and cut-off words). */
    private static function closeness(string $query, array $words, string $name): float
    {
        if ($query === '' || $name === '') {
            return 0.0;
        }

        if (str_contains($name, $query)) {
            return 1.0;
        }

        $nameWords = array_values(array_filter(preg_split('/\s+/u', $name) ?: []));
        $total = 0.0;

        foreach ($words as $word) {
            $bestWord = 0.0;

            foreach ($nameWords as $nameWord) {
                $len = mb_strlen($word);
                $prefix = mb_substr($nameWord, 0, $len);
                $bestWord = max(
                    $bestWord,
                    str_contains($nameWord, $word) ? 0.95 : 0.0,
                    1 - self::distance($word, $nameWord) / max(mb_strlen($word), mb_strlen($nameWord)),
                    $len >= 2 ? 0.95 * (1 - self::distance($word, $prefix) / max($len, 1)) : 0.0,
                );
            }

            $total += $bestWord;
        }

        return $words === [] ? 0.0 : $total / count($words);
    }

    /** Levenshtein distance over characters (not bytes), so Arabic letters count once. */
    private static function distance(string $a, string $b): int
    {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $prev = range(0, count($b));

        foreach ($a as $i => $ca) {
            $cur = [$i + 1];

            foreach ($b as $j => $cb) {
                $cur[$j + 1] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }

            $prev = $cur;
        }

        return $prev[count($b)];
    }

    /**
     * Free-text search over stores: every typed word must be found somewhere in the store — its title, slug, short or full
     * description, keywords, one of its categories, or one of its branches' names, addresses, cities or governorates (Arabic
     * and English both). So one box finds a store by name, by street, by city, by governorate or by what it is about.
     */
    private function matchingTyped($query, string $term)
    {
        $anyLocale = function ($builder, string $column, string $word): void {
            foreach (['ar', 'en'] as $locale) {
                $builder->orWhereRaw(DirectorySearch::translated($column, $locale).' like ?', ['%'.$word.'%']);
            }
        };

        foreach (DirectorySearch::words($term) as $word) {
            $query->where(function ($w) use ($word, $anyLocale): void {
                foreach (['title', 'short_description', 'description', 'meta_keywords'] as $column) {
                    $anyLocale($w, $column, $word);
                }

                $w->orWhereRaw(DirectorySearch::plain('slug').' like ?', ['%'.$word.'%'])
                    ->orWhereHas('categories', fn ($c) => $c->where(fn ($y) => $anyLocale($y, 'name', $word)))
                    ->orWhereHas('branches', function ($b) use ($word, $anyLocale): void {
                        $b->where(function ($x) use ($word, $anyLocale): void {
                            $anyLocale($x, 'name', $word);
                            $anyLocale($x, 'address', $word);
                            $x->orWhereHas('city', fn ($c) => $c->where(fn ($y) => $anyLocale($y, 'name', $word)))
                                ->orWhereHas('governorate', fn ($g) => $g->where(fn ($y) => $anyLocale($y, 'name', $word)));
                        });
                    });
            });
        }

        return $query;
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

        // Visit counter: +1 per opening of the page (a plain query, so `updated_at` is not touched).
        Store::query()->whereKey($store->id)->increment('visits_count');

        $payload = [
            'id' => $store->id,
            'slug' => $store->slug,
            'title' => (string) $store->title,
            'visits' => (int) $store->visits_count + 1,
            'short_description' => $store->short_description ? (string) $store->short_description : null,
            'description' => $this->plainText($store->description),
            'logo' => \App\Support\MobileImage::pick($store->mobile_logo, $store->logo),
            'cover' => $store->header ?: null,
            'gallery' => $gallery,
            /* Videos the admin added to the gallery; the app plays them inline. */
            'videos' => collect($store->gallery)->where('type', 'video')->pluck('url')->take(6)->values()->all(),
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
            /* What shipping costs (null price/threshold = not set, 0 = free); `rates` are the governorates that differ. */
            'shipping' => $store->supports_shipping ? [
                'price' => $store->shipping_price !== null ? (float) $store->shipping_price : null,
                'free_threshold' => $store->free_shipping_threshold !== null ? (float) $store->free_shipping_threshold : null,
                'rates' => $store->shippingRates()->with('governorate')->get()->map(fn ($r) => [
                    'governorate_id' => $r->governorate_id,
                    'name' => (string) $r->governorate?->name,
                    'price' => $r->price !== null ? (float) $r->price : null,
                    'free_threshold' => $r->free_threshold !== null ? (float) $r->free_threshold : null,
                ])->values()->all(),
            ] : null,
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
            ->select(['id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'name', 'address', 'area', 'phone', 'google_location_url'])
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
            'area' => $b->area ? (string) $b->area : null,
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
