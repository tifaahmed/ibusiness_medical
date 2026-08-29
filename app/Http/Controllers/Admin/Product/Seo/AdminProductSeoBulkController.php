<?php

namespace App\Http\Controllers\Admin\Product\Seo;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Product;
use App\Services\Ai\RateLimitException;
use App\Services\ProductSeoGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fill SEO with AI" sweep on the product list.
 *
 * The browser drives it the same way the facility migration importer is driven:
 * call {@see begin()} once to get the work list, then {@see step()} repeatedly
 * with a small slice of slugs until the list is exhausted. Each step is a short
 * request so nothing has to survive a long-running connection on shared hosting.
 */
class AdminProductSeoBulkController extends BaseController
{
    use CreatorScoped;

    /** Products handled per step — each one is an AI round trip. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_PRODUCTS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_PRODUCTS;
    }

    public function __construct(private readonly ProductSeoGenerator $generator) {}

    /**
     * Work list: which products need SEO copy, which need a share image.
     */
    public function begin(Request $request): JsonResponse
    {
        $mode = $request->input('mode') === 'all' ? 'all' : 'missing';

        $products = Product::query()
            ->with('media')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get();

        $seoSlugs = $products
            ->filter(fn (Product $p) => $mode === 'all' || $this->needsSeo($p))
            ->pluck('slug')
            ->values();

        $ogSlugs = $products
            ->filter(fn (Product $p) => $this->needsOgImage($p))
            ->pluck('slug')
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'seo_slugs' => $seoSlugs,
            'og_slugs' => $ogSlugs,
            'total' => $seoSlugs->merge($ogSlugs)->unique()->count(),
        ]);
    }

    /**
     * Process one slice. `slugs` is at most CHUNK long; `do_seo` / `do_og` say
     * which jobs to run for each product in the slice.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'max:'.self::CHUNK],
            'slugs.*' => ['string'],
            'do_seo' => ['nullable', 'boolean'],
            'do_og' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:missing,all'],
        ]);

        $doSeo = filter_var($validated['do_seo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $doOg = filter_var($validated['do_og'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $overwrite = ($validated['mode'] ?? 'missing') === 'all';

        $products = Product::query()
            ->with(['media', 'productType', 'tags'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($products as $product) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            $result = ['slug' => $product->slug, 'seo' => 'skip', 'og' => 'skip'];

            if ($doSeo && ($overwrite || $this->needsSeo($product))) {
                try {
                    $this->fillSeo($product, $overwrite);
                    $result['seo'] = 'ok';
                } catch (RateLimitException $e) {
                    $rateLimited = true;
                    break;
                } catch (RuntimeException $e) {
                    $result['seo'] = 'error';
                    $result['message'] = $e->getMessage();
                } catch (Throwable $e) {
                    Log::error('Bulk product SEO failed', ['slug' => $product->slug, 'error' => $e->getMessage()]);
                    $result['seo'] = 'error';
                    $result['message'] = 'Unexpected error — see the log.';
                }
            }

            if ($doOg && $this->needsOgImage($product)) {
                try {
                    $this->copyLargeImageToOg($product);
                    $result['og'] = 'ok';
                } catch (Throwable $e) {
                    Log::error('Bulk product OG image copy failed', ['slug' => $product->slug, 'error' => $e->getMessage()]);
                    $result['og'] = 'error';
                }
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }

    /**
     * True when neither meta_title nor meta_description has any locale filled.
     */
    private function needsSeo(Product $product): bool
    {
        return $this->allBlank($product->getTranslations('meta_title'))
            || $this->allBlank($product->getTranslations('meta_description'));
    }

    private function needsOgImage(Product $product): bool
    {
        return $product->getFirstMedia('og_image') === null
            && $product->getFirstMedia('large_image') !== null;
    }

    /**
     * @param  array<string, string|null>  $translations
     */
    private function allBlank(array $translations): bool
    {
        foreach ($translations as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return true;
    }

    private function fillSeo(Product $product, bool $overwrite): void
    {
        $seo = $this->generator->generate([
            'name' => $product->getTranslations('name'),
            'short_subject' => $product->getTranslations('short_subject'),
            'description' => $product->getTranslations('description'),
            'product_type' => $product->productType?->getTranslation('name', app()->getLocale())
                ?: $product->productType?->getTranslation('name', 'en'),
            'old_price' => $product->old_price,
            'new_price' => $product->new_price,
            'tags' => $product->tags
                ->map(fn ($tag) => $tag->getTranslation('name', app()->getLocale()) ?: $tag->getTranslation('name', 'en'))
                ->filter()
                ->values()
                ->all(),
        ]);

        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
            $current = $product->getTranslations($field);

            foreach (['ar', 'en'] as $locale) {
                $new = $seo[$field][$locale] ?? '';

                if ($new === '') {
                    continue;
                }

                if ($overwrite || blank($current[$locale] ?? null)) {
                    $product->setTranslation($field, $locale, $new);
                }
            }
        }

        // Saving regenerates the slug from the (Arabic) name; the SEO fields
        // never touch the name, so pin the original slug back if that happened.
        $originalSlug = $product->getOriginal('slug');
        $product->save();

        if ($product->slug !== $originalSlug && filled($originalSlug)) {
            $product->slug = $originalSlug;
            $product->saveQuietly();
        }
    }

    private function copyLargeImageToOg(Product $product): void
    {
        $large = $product->getFirstMedia('large_image');

        if ($large === null) {
            return;
        }

        $product->copyMedia($large->getPath())->toMediaCollection('og_image');
    }
}
