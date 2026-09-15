<?php

namespace App\Http\Controllers\Admin\Governorate\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use App\Services\Ai\RateLimitException;
use App\Services\GovernorateEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix English with AI" sweep on the governorate list. Browser-stepped
 * in the same shape as the facility type English sweep: call {@see begin()}
 * once, then {@see step()} until the work list is done.
 */
class AdminGovernorateEnglishBulkController extends BaseController
{
    use CreatorScoped;

    /** Governorates per step — each is one AI round trip plus its save. */
    private const CHUNK = 5;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_GOVERNORATES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_GOVERNORATES;
    }

    public function __construct(private readonly GovernorateEnglishBackfiller $backfiller) {}

    public function begin(Request $request): JsonResponse
    {
        $slugs = Governorate::query()
            ->with('cities')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get()
            ->filter(fn (Governorate $governorate) => $this->backfiller->hasWork($governorate))
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

        $governorates = Governorate::query()
            ->with('cities')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($governorates as $governorate) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            try {
                $outcome = $this->backfiller->fix($governorate);
                $results[] = [
                    'slug' => $governorate->slug,
                    'applied' => count($outcome['applied']),
                    'errors' => count($outcome['errors']),
                    'state' => 'ok',
                ];
            } catch (RateLimitException $e) {
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $results[] = ['slug' => $governorate->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Bulk governorate English fix failed', ['slug' => $governorate->slug, 'error' => $e->getMessage()]);
                $results[] = ['slug' => $governorate->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => 'Unexpected error — see the log.'];
            }
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
