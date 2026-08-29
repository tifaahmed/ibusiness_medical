<?php

namespace App\Http\Controllers\Admin\Offer\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Offer;
use App\Services\Ai\RateLimitException;
use App\Services\OfferEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix English with AI" sweep on the offer list. Browser-stepped in the
 * same shape as the facility and product English sweeps: call {@see begin()}
 * once to get the work list, then {@see step()} until it is done.
 */
class AdminOfferEnglishBulkController extends BaseController
{
    use CreatorScoped;

    /** Offers per step — each is one AI round trip plus its save. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_OFFERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_OFFERS;
    }

    public function __construct(private readonly OfferEnglishBackfiller $backfiller) {}

    public function begin(Request $request): JsonResponse
    {
        $slugs = Offer::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get()
            ->filter(fn (Offer $offer) => $this->backfiller->hasWork($offer))
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

        $offers = Offer::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($offers as $offer) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            try {
                $outcome = $this->backfiller->fix($offer);
                $results[] = [
                    'slug' => $offer->slug,
                    'applied' => count($outcome['applied']),
                    'errors' => count($outcome['errors']),
                    'state' => 'ok',
                ];
            } catch (RateLimitException $e) {
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $results[] = ['slug' => $offer->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Bulk offer English fix failed', ['slug' => $offer->slug, 'error' => $e->getMessage()]);
                $results[] = ['slug' => $offer->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => 'Unexpected error — see the log.'];
            }
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
