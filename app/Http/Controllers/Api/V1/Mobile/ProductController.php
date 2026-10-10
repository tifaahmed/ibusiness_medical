<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\Lookups;
use App\Http\Controllers\Api\V1\Mobile\Concerns\RespondsCompactly;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductType;
use App\Support\DirectorySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile app's product API — see `FacilityController` for the ground rules.
 *
 * Never selects or ships `cost_price`, `profit_price` or `admin_note`.
 */
class ProductController extends Controller
{
    use Lookups;
    use RespondsCompactly;

    private const PER_PAGE = 12;

    private const PRICE = 'coalesce(products.new_price, products.old_price)';

    /** Product types that hold at least one visible product, with how many. */
    public function types(Request $request): JsonResponse
    {
        $types = Cache::remember('mobile:product-types:'.app()->getLocale(), 600, fn () => ProductType::query()
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->get(['id', 'name', 'slug'])
            ->filter(fn ($t) => $t->products_count > 0)
            ->sortByDesc('products_count')
            ->map(fn ($t) => ['id' => $t->id, 'name' => (string) $t->name, 'slug' => $t->slug, 'count' => (int) $t->products_count])
            ->values()
            ->all());

        return $this->respond($request, ['data' => $types], 600);
    }

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,discount'],
            'slugs' => ['nullable', 'array', 'max:60'],
            'slugs.*' => ['string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $price = self::PRICE;

        $query = Product::query()
            ->select([
                'products.id', 'products.slug', 'products.name', 'products.short_subject',
                'products.old_price', 'products.new_price', 'products.product_type_id',
                'products.store_id', 'products.is_purchasable', 'products.is_accessible',
            ])
            ->with([
                'media' => fn ($q) => $q->whereIn('collection_name', ['small_image', 'large_image']),
                'store:id,slug,title',
            ])
            // Named products are a basket being re-priced, so they bypass the
            // shop-window switch (same rule as the web catalogue).
            ->when(empty($v['slugs']), fn (Builder $q) => $q->visibleInShop(), fn (Builder $q) => $q->whereIn('products.slug', $v['slugs']))
            ->when(! empty($v['q']), function (Builder $q) use ($v) {
                $name = DirectorySearch::translated('name', app()->getLocale());

                foreach (DirectorySearch::words($v['q']) as $word) {
                    $q->where(fn ($w) => $w->whereRaw("{$name} like ?", ['%'.$word.'%'])->orWhere('products.slug', 'like', '%'.$word.'%'));
                }
            })
            ->when(! empty($v['type']), fn (Builder $q) => $q->where('products.product_type_id', (int) $v['type']))
            ->when(! empty($v['store_id']), fn (Builder $q) => $q->where('products.store_id', (int) $v['store_id']));

        match ($v['sort'] ?? 'newest') {
            'price_asc' => $query->orderByRaw("{$price} is null, {$price} asc")->orderBy('products.id'),
            'price_desc' => $query->orderByRaw("{$price} is null, {$price} desc")->orderBy('products.id'),
            'discount' => $query->orderByRaw('case when old_price > 0 and new_price > 0 and new_price < old_price then (old_price - new_price) / old_price else 0 end desc')->orderByDesc('products.id'),
            default => $query->orderByDesc('products.id'),
        };

        $page = $query->simplePaginate($v['per_page'] ?? self::PER_PAGE)->withQueryString();
        $types = collect($this->productTypeNames());

        $data = $page->getCollection()->map(fn (Product $p) => $this->card($p, $types))->values()->all();

        return $this->respond($request, ['data' => $data, 'has_more' => $page->hasMorePages()], 120);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        // 404, not 403: whether a closed product exists is not the visitor's business.
        abort_unless($product->is_accessible, Response::HTTP_NOT_FOUND);

        $product->load([
            'media' => fn ($q) => $q->whereIn('collection_name', ['small_image', 'large_image']),
            'galleries',
            'store:id,slug,title',
        ]);

        $types = collect($this->productTypeNames());

        $gallery = array_values(array_unique(array_filter([
            $product->getFirstMediaUrl('large_image'),
            $product->getFirstMediaUrl('small_image'),
            ...array_column($product->gallery, 'url'),
        ])));

        $payload = $this->card($product, $types) + [
            'description' => $this->plainText($product->description),
            'gallery' => $gallery,
        ];

        return $this->respond($request, $payload, 300);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $types
     * @return array<string, mixed>
     */
    private function card(Product $p, $types): array
    {
        $old = $p->old_price !== null ? (float) $p->old_price : null;
        $new = $p->new_price !== null ? (float) $p->new_price : null;
        $selling = $new ?? $old;
        $discount = ($old && $new && $old > 0 && $new > 0 && $new < $old) ? (int) round((($old - $new) / $old) * 100) : null;

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => (string) $p->name,
            'subtitle' => $p->short_subject ? (string) $p->short_subject : null,
            'price' => $selling,
            'old_price' => $discount !== null ? $old : null,
            'discount' => $discount,
            'image' => $p->getFirstMediaUrl('small_image') ?: $p->getFirstMediaUrl('large_image'),
            'type' => $p->product_type_id && $types->has($p->product_type_id) ? ['id' => $p->product_type_id, 'name' => $types->get($p->product_type_id)] : null,
            'store' => $p->store ? ['id' => $p->store->id, 'slug' => $p->store->slug, 'title' => (string) $p->store->title] : null,
            'purchasable' => (bool) $p->is_purchasable,
        ];
    }

    /** @return array<int, string> */
    private function productTypeNames(): array
    {
        return Cache::remember('mobile:product-type-names:'.app()->getLocale(), 3600, fn () => ProductType::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($t) => [$t->id => (string) $t->name])
            ->all());
    }
}
