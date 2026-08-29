<?php

namespace App\Http\Controllers\Admin\Product\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Product;
use App\Services\Ai\RateLimitException;
use App\Services\ProductEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix English with AI" sweep on the product list. Browser-stepped in the
 * same shape as the facility English sweep and the product SEO sweep: call
 * {@see begin()} once, then {@see step()} until the work list is done.
 */
class AdminProductEnglishBulkController extends BaseController
{
    use CreatorScoped;

    /** Products per step — each is one AI round trip plus its save. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_PRODUCTS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_PRODUCTS;
    }

    public function __construct(private readonly ProductEnglishBackfiller $backfiller) {}

    public function begin(Request $request): JsonResponse
    {
        $slugs = Product::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get()
            ->filter(fn (Product $product) => $this->backfiller->hasWork($product))
            ->pluck('slug')
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'slugs' => $slugs,
            'total' => $slugs->count(),
        ]);
    }

    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'max:'.self::CHUNK],
            'slugs.*' => ['string'],
        ]);

        $products = Product::query()
            ->with('productType')
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

            try {
                $outcome = $this->backfiller->fix($product);
                $results[] = [
                    'slug' => $product->slug,
                    'applied' => count($outcome['applied']),
                    'errors' => count($outcome['errors']),
                    'state' => 'ok',
                ];
            } catch (RateLimitException $e) {
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $results[] = ['slug' => $product->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Bulk product English fix failed', ['slug' => $product->slug, 'error' => $e->getMessage()]);
                $results[] = ['slug' => $product->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => 'Unexpected error — see the log.'];
            }
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
