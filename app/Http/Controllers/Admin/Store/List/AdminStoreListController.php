<?php

namespace App\Http\Controllers\Admin\Store\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);
        $search = $filters['search'];

        $stores = Store::query()
            ->with('categories:id,name')
            ->withCount(['branches', 'products'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->tap(fn ($q) => $this->applyFilters($q, $filters))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Store $store) => [
                'id' => $store->id,
                'title' => $store->title,
                'categories' => $store->categories->map(fn ($c) => $c->getTranslations('name'))->values(),
                'logo' => $store->logo,
                'online_only' => (bool) $store->online_only,
                'supports_shipping' => (bool) $store->supports_shipping,
                'branches_count' => $store->branches_count,
                'products_count' => $store->products_count,
                'created_at' => $store->created_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Admin/Store/List/StoreListView', [
            'stores' => $stores,
            'filters' => $filters,
            'filterOptions' => [
                'tags' => \App\Models\Tag::query()->orderBy('id')->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getTranslations('name')])->values(),
                'creators' => \App\Models\User::query()->whereIn('id', Store::query()->whereNotNull('created_by')->select('created_by'))->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            ],
            'exportOptions' => [
                'categories' => \App\Models\StoreCategory::query()->orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->getTranslations('name')])->values(),
                'sales' => \App\Models\Sales::pickerOptions(),
                'governorates' => \App\Models\Governorate::query()->orderBy('id')->get()->map(fn ($g) => ['id' => $g->id, 'name' => $g->getTranslations('name')])->values(),
                'cities' => \App\Models\City::query()->orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'governorate_id' => $c->governorate_id, 'name' => $c->getTranslations('name')])->values(),
                'columns' => collect(\App\Support\StoreSpreadsheet::storeColumns())->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label']])->values(),
            ],
            'canManage' => $request->user()?->hasAnyPermission([
                UserPermissionEnum::MANAGE_STORES,
                UserPermissionEnum::MANAGE_OWN_STORES,
            ]) ?? false,
        ]);
    }

    private const PRESENCE = ['with', 'without'];

    private const YESNO = ['yes', 'no'];

    /** @return array<string, mixed> Every value is whitelisted here, so the clauses below never see raw query strings. */
    protected function getFilters(Request $request): array
    {
        $in = fn (string $key, array $allowed) => in_array($request->input($key), $allowed, true) ? $request->input($key) : '';
        $id = fn (string $key) => ctype_digit((string) $request->input($key)) ? (string) $request->input($key) : '';
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input($key)) ? (string) $request->input($key) : '';
        $num = fn (string $key) => is_numeric($request->input($key)) ? (string) $request->input($key) : '';

        $type = $in('store_type', ['online', 'physical', 'physical_online', 'physical_only']);

        // An online-only store has no branches by design: the branch filters are dropped here, so the page, its props
        // and the query all agree that they are not set (a stale URL cannot narrow the results).
        $branch = fn (string $v) => $type === 'online' ? '' : $v;

        return [
            'search' => $request->string('search')->toString(),
            'category_id' => $id('category_id'),
            'category_presence' => $in('category_presence', self::PRESENCE),
            'tag_id' => $id('tag_id'),
            'tags_presence' => $in('tags_presence', self::PRESENCE),
            'sales_id' => $id('sales_id'),
            'sales_presence' => $in('sales_presence', self::PRESENCE),
            'managers_presence' => $in('managers_presence', self::PRESENCE),
            'governorate_id' => $branch($id('governorate_id')),
            'city_id' => $branch($id('city_id')),
            // online = online_only; physical = has (or should have) branches; physical_online = physical that ALSO ships; physical_only = physical that does not ship.
            'store_type' => $type,
            'branches_presence' => $branch($in('branches_presence', self::PRESENCE)),
            'branches_min' => $branch(ctype_digit((string) $request->input('branches_min')) ? (string) $request->input('branches_min') : ''),
            'branches_max' => $branch(ctype_digit((string) $request->input('branches_max')) ? (string) $request->input('branches_max') : ''),
            'branches_missing' => $branch($in('branches_missing', ['governorate', 'city', 'either', 'both'])),
            'products_presence' => $in('products_presence', self::PRESENCE),
            'supports_shipping' => $in('supports_shipping', self::YESNO),
            'ships_everywhere' => $in('ships_everywhere', self::YESNO),
            'shipping_governorate_id' => $id('shipping_governorate_id'),
            'offer_presence' => $in('offer_presence', self::PRESENCE),
            'offer_format' => $in('offer_format', ['round', 'decimal']),
            'offer_min' => $num('offer_min'),
            'offer_max' => $num('offer_max'),
            'contracted_from' => $date('contracted_from'),
            'contracted_to' => $date('contracted_to'),
            'contract_presence' => $in('contract_presence', self::PRESENCE),
            'logo_presence' => $in('logo_presence', self::PRESENCE),
            'coupons_presence' => $in('coupons_presence', self::PRESENCE),
            'website_presence' => $in('website_presence', self::PRESENCE),
            'app_presence' => $in('app_presence', self::PRESENCE),
            'social_presence' => $in('social_presence', self::PRESENCE),
            'seo_missing' => $in('seo_missing', ['yes']),
            'created_by' => $id('created_by'),
            'created_from' => $date('created_from'),
            'created_to' => $date('created_to'),
        ];
    }

    private function applyFilters($q, array $f): void
    {
        if ($f['store_type'] === 'online') {
            // Branch filters are meaningless for online-only stores; a stale URL must not turn them into "no results".
            foreach (['governorate_id', 'city_id', 'branches_presence', 'branches_missing', 'branches_min', 'branches_max'] as $k) {
                $f[$k] = '';
            }
        }

        // An online-only store has no branches by design, so the branch filters (place, count, "no branches") never apply to it.
        $branchFilters = $f['branches_presence'] !== '' || $f['branches_missing'] !== '' || $f['branches_min'] !== '' || $f['branches_max'] !== '';
        $physicalOnly = $branchFilters && $f['store_type'] !== 'online';

        $present = fn ($column) => "{$column} is not null and JSON_LENGTH({$column}) > 0";

        $q->when($f['search'] !== '', function ($query) use ($f) {
            $term = "%{$f['search']}%";
            $query->where(fn ($b) => $b->where('title->ar', 'like', $term)->orWhere('title->en', 'like', $term)->orWhere('slug', 'like', $term));
        })
            ->when($f['category_id'] !== '', fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('store_categories.id', (int) $f['category_id'])))
            ->when($f['category_presence'] === 'with', fn ($q) => $q->whereHas('categories'))
            ->when($f['category_presence'] === 'without', fn ($q) => $q->whereDoesntHave('categories'))
            ->when($f['tag_id'] !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', (int) $f['tag_id'])))
            ->when($f['tags_presence'] === 'with', fn ($q) => $q->whereHas('tags'))
            ->when($f['tags_presence'] === 'without', fn ($q) => $q->whereDoesntHave('tags'))
            ->when($f['sales_id'] !== '', fn ($q) => $q->where('sales_id', (int) $f['sales_id']))
            ->when($f['sales_presence'] === 'with', fn ($q) => $q->whereNotNull('sales_id'))
            ->when($f['sales_presence'] === 'without', fn ($q) => $q->whereNull('sales_id'))
            ->when($f['managers_presence'] === 'with', fn ($q) => $q->whereHas('managers'))
            ->when($f['managers_presence'] === 'without', fn ($q) => $q->whereDoesntHave('managers'))
            ->when($f['governorate_id'] !== '', fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('governorate_id', (int) $f['governorate_id'])))
            ->when($f['city_id'] !== '', fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('city_id', (int) $f['city_id'])))
            ->when($f['store_type'] === 'online', fn ($q) => $q->where('online_only', true))
            ->when(in_array($f['store_type'], ['physical', 'physical_online', 'physical_only'], true), fn ($q) => $q->where('online_only', false))
            ->when($f['store_type'] === 'physical_online', fn ($q) => $q->where('supports_shipping', true))
            ->when($f['store_type'] === 'physical_only', fn ($q) => $q->where('supports_shipping', false))
            ->when($physicalOnly, fn ($q) => $q->where('online_only', false))
            // "Has branches" / "no branches" is a statement about physical stores: a physical store with none is a gap, an online one is not.
            ->when($f['branches_presence'] === 'with', fn ($q) => $q->has('branches'))
            ->when($f['branches_presence'] === 'without', fn ($q) => $q->doesntHave('branches'))
            ->when($f['branches_min'] !== '', fn ($q) => $q->has('branches', '>=', (int) $f['branches_min']))
            ->when($f['branches_max'] !== '', fn ($q) => $q->has('branches', '<=', (int) $f['branches_max']))
            ->when($f['branches_missing'] === 'governorate', fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereNull('governorate_id')))
            ->when($f['branches_missing'] === 'city', fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereNull('city_id')))
            ->when($f['branches_missing'] === 'either', fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereNull('governorate_id')->orWhereNull('city_id')))
            ->when($f['branches_missing'] === 'both', fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereNull('governorate_id')->whereNull('city_id')))
            ->when($f['products_presence'] === 'with', fn ($q) => $q->has('products'))
            ->when($f['products_presence'] === 'without', fn ($q) => $q->doesntHave('products'))
            ->when($f['supports_shipping'] !== '', fn ($q) => $q->where('supports_shipping', $f['supports_shipping'] === 'yes'))
            ->when($f['ships_everywhere'] !== '', fn ($q) => $q->where('supports_shipping', true)->where('ships_everywhere', $f['ships_everywhere'] === 'yes'))
            ->when($f['shipping_governorate_id'] !== '', fn ($q) => $q->where('supports_shipping', true)->where(fn ($s) => $s->where('ships_everywhere', true)->orWhereHas('shippingGovernorates', fn ($g) => $g->where('governorates.id', (int) $f['shipping_governorate_id']))))
            ->when($f['offer_presence'] === 'with', fn ($q) => $q->where(fn ($b) => $b->whereNotNull('offer_percent_from')->orWhereNotNull('offer_percent_to')))
            ->when($f['offer_presence'] === 'without', fn ($q) => $q->whereNull('offer_percent_from')->whereNull('offer_percent_to'))
            ->when($f['offer_format'] === 'round', fn ($q) => $q->where(fn ($b) => $b->whereNotNull('offer_percent_from')->orWhereNotNull('offer_percent_to'))->whereRaw('COALESCE(offer_percent_from, 0) = FLOOR(COALESCE(offer_percent_from, 0)) and COALESCE(offer_percent_to, 0) = FLOOR(COALESCE(offer_percent_to, 0))'))
            ->when($f['offer_format'] === 'decimal', fn ($q) => $q->where(fn ($b) => $b->whereRaw('offer_percent_from != FLOOR(offer_percent_from)')->orWhereRaw('offer_percent_to != FLOOR(offer_percent_to)')))
            ->when($f['offer_min'] !== '', fn ($q) => $q->where(fn ($b) => $b->where('offer_percent_to', '>=', (float) $f['offer_min'])->orWhere('offer_percent_from', '>=', (float) $f['offer_min'])))
            ->when($f['offer_max'] !== '', fn ($q) => $q->where(fn ($b) => $b->where('offer_percent_from', '<=', (float) $f['offer_max'])->orWhere('offer_percent_to', '<=', (float) $f['offer_max'])))
            ->when($f['contracted_from'] !== '', fn ($q) => $q->whereDate('contracted_at', '>=', $f['contracted_from']))
            ->when($f['contracted_to'] !== '', fn ($q) => $q->whereDate('contracted_at', '<=', $f['contracted_to']))
            ->when($f['contract_presence'] === 'with', fn ($q) => $q->whereHas('media', fn ($m) => $m->where('collection_name', 'contract')))
            ->when($f['contract_presence'] === 'without', fn ($q) => $q->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'contract')))
            ->when($f['logo_presence'] === 'with', fn ($q) => $q->whereHas('media', fn ($m) => $m->where('collection_name', 'logo')))
            ->when($f['logo_presence'] === 'without', fn ($q) => $q->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'logo')))
            ->when($f['coupons_presence'] === 'with', fn ($q) => $q->whereRaw($present('coupons')))
            ->when($f['coupons_presence'] === 'without', fn ($q) => $q->whereRaw('not ('.$present('coupons').')'))
            ->when($f['website_presence'] === 'with', fn ($q) => $q->whereRaw($present('websites')))
            ->when($f['website_presence'] === 'without', fn ($q) => $q->whereRaw('not ('.$present('websites').')'))
            ->when($f['app_presence'] === 'with', fn ($q) => $q->where(fn ($b) => $b->where('app_store_url', '!=', '')->orWhere('google_play_url', '!=', '')))
            ->when($f['app_presence'] === 'without', fn ($q) => $q->where(fn ($b) => $b->whereNull('app_store_url')->orWhere('app_store_url', ''))->where(fn ($b) => $b->whereNull('google_play_url')->orWhere('google_play_url', '')))
            ->when($f['social_presence'] === 'with', fn ($q) => $q->whereRaw($present('social_links')))
            ->when($f['social_presence'] === 'without', fn ($q) => $q->whereRaw('not ('.$present('social_links').')'))
            ->when($f['seo_missing'] === 'yes', fn ($q) => $q->where(fn ($b) => $b->whereNull('meta_title')->orWhere('meta_title', '')->orWhereNull('meta_description')->orWhere('meta_description', '')))
            ->when($f['created_by'] !== '', fn ($q) => $q->where('created_by', (int) $f['created_by']))
            ->when($f['created_from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $f['created_from']))
            ->when($f['created_to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $f['created_to']));
    }
}
