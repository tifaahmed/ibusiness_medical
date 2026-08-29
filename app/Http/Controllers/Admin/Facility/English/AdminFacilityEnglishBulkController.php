<?php

namespace App\Http\Controllers\Admin\Facility\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Services\Ai\RateLimitException;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix English with AI" sweep on the facility list. Browser-stepped in the
 * same shape as the product SEO sweep and the migration importer: {@see begin()}
 * once, then {@see step()} until the work list is done.
 */
class AdminFacilityEnglishBulkController extends BaseController
{
    use CreatorScoped;

    /** Facilities per step — each is one AI round trip plus its saves. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    public function begin(Request $request): JsonResponse
    {
        $slugs = Facility::query()
            ->with(['branches:id,facility_id,name,address,governorate_id,city_id', 'branches.governorate:id,name', 'branches.city:id,name'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get()
            ->filter(fn (Facility $facility) => $this->backfiller->hasWork($facility))
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

        $facilities = Facility::query()
            ->with(['facilityType', 'branches.governorate', 'branches.city'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($facilities as $facility) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            try {
                $outcome = $this->backfiller->fix($facility);
                $results[] = [
                    'slug' => $facility->slug,
                    'applied' => count($outcome['applied']),
                    'errors' => count($outcome['errors']),
                    'state' => 'ok',
                ];
            } catch (RateLimitException $e) {
                // The whole per-minute window is blocked — stop here and let the
                // browser wait, then retry this slice.
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $results[] = ['slug' => $facility->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Bulk facility English fix failed', ['slug' => $facility->slug, 'error' => $e->getMessage()]);
                $results[] = ['slug' => $facility->slug, 'applied' => 0, 'errors' => 1, 'state' => 'error', 'message' => 'Unexpected error — see the log.'];
            }
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
