<?php

namespace App\Http\Controllers\Admin\FacilityType\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityType;
use App\Services\Ai\RateLimitException;
use App\Services\FacilityTypeEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix English with AI" sweep on the facility type list. Browser-stepped
 * in the same shape as the facility and product English sweeps: call
 * {@see begin()} once, then {@see step()} until the work list is done.
 */
class AdminFacilityTypeEnglishBulkController extends BaseController
{
    use CreatorScoped;

    /** Facility types per step — each is one AI round trip plus its save. */
    private const CHUNK = 5;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(private readonly FacilityTypeEnglishBackfiller $backfiller) {}

    public function begin(Request $request): JsonResponse
    {
        $slugs = FacilityType::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get()
            ->filter(fn (FacilityType $facilityType) => $this->backfiller->hasWork($facilityType))
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

        $facilityTypes = FacilityType::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($facilityTypes as $facilityType) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            try {
                $outcome = $this->backfiller->fix($facilityType);
                $results[] = [
                    'slug' => $facilityType->slug,
                    'applied' => count($outcome['applied']),
                    'errors' => count($outcome['errors']),
                    'state' => 'ok',
                ];
            } catch (RateLimitException $e) {
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $results[] = ['slug' => $facilityType->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Bulk facility type English fix failed', ['slug' => $facilityType->slug, 'error' => $e->getMessage()]);
                $results[] = ['slug' => $facilityType->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => 'Unexpected error — see the log.'];
            }
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
