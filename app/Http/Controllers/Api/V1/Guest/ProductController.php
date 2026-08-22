<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\ProductDetailResource;
use App\Http\Resources\Api\V1\Guest\ProductResource;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public product catalogue, read by the Deilar storefront.
 *
 * Public and key-less like the facilities endpoints beside it: nothing here
 * identifies a member, and the storefront is a separate application that
 * should not hold a credential to browse a shop window. Only `X-Locale`
 * travels, which is what gets product and category names back in the language
 * the visitor is reading.
 */
class ProductController extends Controller
{
    /** The storefront grid is four across; twelve fills three rows of it. */
    private const DEFAULT_PER_PAGE = 12;

    /** A page big enough to refill a whole cart, and no bigger. */
    private const MAX_PER_PAGE = 60;

    /** Past this the filter sidebar is a scroll of its own rather than a list. */
    private const MAX_FILTER_OPTIONS = 60;

    /** How many names are worth shipping for the search suggestions. */
    private const MAX_SUGGESTIONS = 400;

    /**
     * One page of the catalogue, with everything the sidebar needs to filter it.
     *
     * The whole page comes out of this one call — grid, categories, tags, the
     * price bounds and the search suggestions — so a storefront paints in a
     * single round trip rather than fetching its own filters afterwards.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $products = $this->filtered($filters)
            ->with(['productType:id,name,slug', 'tags:id,name,icon,color', 'media'])
            ->paginate($this->perPage($request))
            ->withQueryString();

        return response()->json([
            'products' => ProductResource::collection($products)->response()->getData(true),
            'filters' => $filters,
            'product_types' => $this->productTypes($filters),
            'tags' => $this->tags($filters),
            'price_range' => $this->priceRange(),
            'product_names' => $this->productNames(),
        ]);
    }

    /**
     * One product, with the products worth looking at next.
     *
     * Related products come back in the same response for the same reason the
     * filters do above: the "similar products" rail sits on the page being
     * asked for, so fetching it separately would cost a second round trip to
     * paint one screen.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['productType:id,name,slug', 'tags:id,name,icon,color', 'galleries', 'media']);

        return response()->json([
            'product' => new ProductDetailResource($product),
            'related' => ProductResource::collection($this->related($product)),
        ]);
    }

    /**
     * The filters as they arrive in the query string.
     *
     * Echoed back in the response so a consumer can render "you searched for…"
     * without re-parsing the URL it just asked with.
     *
     * @return array{search: string, product_type_id: ?int, tag_id: ?int, min_price: ?float, max_price: ?float, sort: string, slugs: list<string>}
     */
    private function filters(Request $request): array
    {
        $slugs = $request->input('slugs', []);

        return [
            'search' => trim((string) $request->input('search', '')),
            'product_type_id' => $request->filled('product_type_id') ? (int) $request->input('product_type_id') : null,
            'tag_id' => $request->filled('tag_id') ? (int) $request->input('tag_id') : null,
            'min_price' => $request->filled('min_price') ? (float) $request->input('min_price') : null,
            'max_price' => $request->filled('max_price') ? (float) $request->input('max_price') : null,
            'sort' => $this->sort($request),
            /*
             * Named products rather than a search: this is how a storefront
             * re-prices a whole basket in one call instead of one request per
             * line. Capped at a page's worth, which is what the request can
             * return anyway.
             */
            'slugs' => collect(is_array($slugs) ? $slugs : [$slugs])
                ->filter(fn ($slug) => is_string($slug) && $slug !== '')
                ->map(fn (string $slug) => trim($slug))
                ->unique()
                ->take(self::MAX_PER_PAGE)
                ->values()
                ->all(),
        ];
    }

    /**
     * The requested ordering, or the default.
     *
     * A whitelist, not a column name from the request: this ends up in an
     * `ORDER BY`.
     */
    private function sort(Request $request): string
    {
        $sort = (string) $request->input('sort', 'newest');

        return in_array($sort, ['newest', 'price_asc', 'price_desc', 'discount', 'name'], true)
            ? $sort
            : 'newest';
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * The catalogue narrowed by the filters and put in order.
     *
     * @param  array{search: string, product_type_id: ?int, tag_id: ?int, min_price: ?float, max_price: ?float, sort: string, slugs: list<string>}  $filters
     * @return Builder<Product>
     */
    private function filtered(array $filters): Builder
    {
        $query = Product::query();

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'], $this->priceExpression());

        return $query;
    }

    /**
     * Narrow a product query by the filters, optionally ignoring one of them.
     *
     * `$except` is what makes the sidebar's counts work. A facet is counted
     * with its OWN dimension left out — the tag list is counted under the
     * search, the category and the price but not under the chosen tag — so
     * picking a tag narrows the categories beside it without collapsing the
     * tag list to the one tag already picked.
     *
     * Every column is table-qualified because this same builder runs inside a
     * `withCount` subquery on `tags` and on `product_types`, and `name`,
     * `slug` and `id` all exist on both sides of that join.
     *
     * @param  Builder<Product>  $query
     * @param  array{search: string, product_type_id: ?int, tag_id: ?int, min_price: ?float, max_price: ?float, sort: string, slugs: list<string>}  $filters
     * @param  string|null  $except  a filter key to skip: 'product_type_id' or 'tag_id'
     */
    private function applyFilters(Builder $query, array $filters, ?string $except = null): void
    {
        $price = $this->priceExpression();

        $query
            ->when($filters['slugs'] !== [], fn (Builder $q) => $q->whereIn('products.slug', $filters['slugs']))
            ->when($filters['search'] !== '', fn (Builder $q) => $this->applySearch($q, $filters['search']))
            ->when(
                $except !== 'product_type_id' && $filters['product_type_id'],
                fn (Builder $q) => $q->where('products.product_type_id', $filters['product_type_id']),
            )
            ->when(
                $except !== 'tag_id' && $filters['tag_id'],
                fn (Builder $q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $filters['tag_id'])),
            )
            ->when($filters['min_price'] !== null, fn (Builder $q) => $q->whereRaw("{$price} >= ?", [$filters['min_price']]))
            ->when($filters['max_price'] !== null, fn (Builder $q) => $q->whereRaw("{$price} <= ?", [$filters['max_price']]));
    }

    /**
     * Match every word of the search, in the name or the slug.
     *
     * Arabic is normalised on both sides — the alef and ya forms are typed
     * interchangeably, so a search for "اشعة" has to find "أشعة". One-letter
     * words are dropped as noise unless they are all the visitor typed.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        $locale = app()->getLocale();
        $normalized = $this->normalizeSearch($search);

        $words = collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn ($word) => trim($word))
            ->filter(fn ($word) => mb_strlen($word) > 1)
            ->values();

        if ($words->isEmpty()) {
            $words = collect([$normalized]);
        }

        $nameExpression = $this->normalizedJsonExpression('products.name', $locale);
        $subjectExpression = $this->normalizedJsonExpression('products.short_subject', $locale);

        foreach ($words as $word) {
            $query->where(function (Builder $inner) use ($word, $nameExpression, $subjectExpression) {
                $inner->whereRaw("{$nameExpression} like ?", ['%'.$word.'%'])
                    ->orWhereRaw("{$subjectExpression} like ?", ['%'.$word.'%'])
                    ->orWhere('products.slug', 'like', '%'.$word.'%');
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort, string $price): void
    {
        match ($sort) {
            /*
             * NULLS LAST in both directions: a product with no price at all is
             * neither the cheapest nor the dearest, and letting it head the
             * "price, low to high" page would make the sort look broken.
             */
            'price_asc' => $query->orderByRaw("{$price} is null, {$price} asc")->orderBy('id'),
            'price_desc' => $query->orderByRaw("{$price} is null, {$price} desc")->orderBy('id'),
            'discount' => $query->orderByRaw(
                'case when old_price > 0 and new_price > 0 and new_price < old_price'
                .' then (old_price - new_price) / old_price else 0 end desc'
            )->orderByDesc('id'),
            'name' => $query->orderByRaw($this->jsonExpression('products.name', app()->getLocale()).' asc')->orderBy('id'),
            default => $query->latest('id'),
        };
    }

    /**
     * The categories to offer, with how many products sit in each.
     *
     * Counted under the filters that are actually applied, minus this facet's
     * own — so a category only appears if a currently-matching product sits in
     * it, and the count beside it is the number the grid would really show.
     * Options that would empty the grid are dropped rather than greyed out.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, slug: string, name: mixed, products_count: int}>
     */
    private function productTypes(array $filters): array
    {
        return ProductType::query()
            ->withCount(['products' => fn (Builder $query) => $this->applyFilters($query, $filters, 'product_type_id')])
            ->tap(fn ($query) => $this->keepSelected($query, 'product_types.id', $filters['product_type_id']))
            ->orderByDesc('products_count')
            ->limit(self::MAX_FILTER_OPTIONS)
            ->get()
            ->map(fn (ProductType $type) => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'products_count' => (int) $type->products_count,
            ])
            ->values()
            ->all();
    }

    /**
     * The tags to offer, with how many products carry each.
     *
     * Tags are shared with facilities and services, so counting through the
     * `products` relation is also what keeps a clinic's tags out of a shop
     * sidebar: a tag no product carries counts zero and is dropped.
     *
     * Counted under the other filters but not under the chosen tag — see
     * `applyFilters()` — so picking one leaves the rest of the list intact
     * instead of collapsing it to the single tag already picked.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, name: mixed, icon: ?string, color: ?string, products_count: int}>
     */
    private function tags(array $filters): array
    {
        return Tag::query()
            ->withCount(['products' => fn (Builder $query) => $this->applyFilters($query, $filters, 'tag_id')])
            ->tap(fn ($query) => $this->keepSelected($query, 'tags.id', $filters['tag_id']))
            ->orderByDesc('products_count')
            ->limit(self::MAX_FILTER_OPTIONS)
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'icon' => $tag->icon,
                'color' => $tag->color,
                'products_count' => (int) $tag->products_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Drop every option that would empty the grid — except the one already
     * chosen.
     *
     * The selected option is kept at any count so the sidebar can still show
     * which filter is on. Without it, a combination that matches nothing would
     * drop the chosen option out of the list and leave the visitor looking at
     * an empty grid with no visible reason for it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function keepSelected(Builder $query, string $idColumn, ?int $selected): void
    {
        if ($selected === null) {
            $query->having('products_count', '>', 0);

            return;
        }

        $query->havingRaw("products_count > 0 or {$idColumn} = ?", [$selected]);
    }

    /**
     * What the cheapest and dearest products cost.
     *
     * Deliberately measured over the WHOLE catalogue rather than the filtered
     * page: these are the ends of a price slider, and bounds that moved every
     * time the slider did would be impossible to drag back out of.
     *
     * @return array{min: ?float, max: ?float}
     */
    private function priceRange(): array
    {
        $price = $this->priceExpression();

        $row = Product::query()
            ->selectRaw("min({$price}) as min_price, max({$price}) as max_price")
            ->first();

        return [
            'min' => $row?->min_price === null ? null : (float) $row->min_price,
            'max' => $row?->max_price === null ? null : (float) $row->max_price,
        ];
    }

    /**
     * Every product name, for the search box's suggestions.
     *
     * @return list<array{slug: string, name: mixed}>
     */
    private function productNames(): array
    {
        return Product::query()
            ->select(['slug', 'name'])
            ->limit(self::MAX_SUGGESTIONS)
            ->get()
            ->map(fn (Product $product) => [
                'slug' => $product->slug,
                'name' => $product->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The products to show underneath this one.
     *
     * Its own category first, then anything sharing a tag with it, then the
     * newest arrivals — so the rail is full even for the only product in a
     * category nobody has tagged.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    private function related(Product $product)
    {
        $limit = 8;
        $with = ['productType:id,name,slug', 'tags:id,name,icon,color', 'media'];

        $related = Product::query()
            ->with($with)
            ->where('id', '!=', $product->id)
            ->when(
                $product->product_type_id,
                fn (Builder $q, int $typeId) => $q->where('product_type_id', $typeId),
                fn (Builder $q) => $q->whereRaw('1 = 0'),
            )
            ->latest('id')
            ->limit($limit)
            ->get();

        if ($related->count() >= $limit) {
            return $related;
        }

        $tagIds = $product->tags->pluck('id');

        if ($tagIds->isNotEmpty()) {
            $related = $related->concat(
                Product::query()
                    ->with($with)
                    ->whereNotIn('id', $related->pluck('id')->push($product->id))
                    ->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds))
                    ->latest('id')
                    ->limit($limit - $related->count())
                    ->get()
            );
        }

        if ($related->count() >= $limit) {
            return $related;
        }

        return $related->concat(
            Product::query()
                ->with($with)
                ->whereNotIn('id', $related->pluck('id')->push($product->id))
                ->latest('id')
                ->limit($limit - $related->count())
                ->get()
        );
    }

    /**
     * What a product costs, as SQL: the discounted price when there is one.
     */
    private function priceExpression(): string
    {
        return 'coalesce(products.new_price, products.old_price)';
    }

    /**
     * A translatable column read for one locale, as SQL.
     */
    private function jsonExpression(string $column, string $locale): string
    {
        /*
         * Qualified names are split rather than back-ticked whole: these
         * expressions run inside `withCount` subqueries where `name` and
         * `slug` exist on both joined tables, so "products.name" has to come
         * out as `products`.`name` and not as a single `products.name`
         * identifier, which MySQL reads as a column with a dot in it.
         */
        $quoted = collect(explode('.', $column))
            ->map(fn (string $part) => '`'.$part.'`')
            ->implode('.');

        return "json_unquote(json_extract({$quoted}, '$.".$locale."'))";
    }

    /**
     * The same, with the interchangeable Arabic letter forms folded together
     * so a search normalised the same way can match it.
     */
    private function normalizedJsonExpression(string $column, string $locale): string
    {
        $expression = $this->jsonExpression($column, $locale);

        return "replace(replace(replace(replace({$expression}, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";
    }

    /**
     * Fold a search term the same way the columns are folded above.
     */
    private function normalizeSearch(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $term);
        $term = str_replace('ى', 'ي', $term);

        return preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $term) ?? $term;
    }
}
