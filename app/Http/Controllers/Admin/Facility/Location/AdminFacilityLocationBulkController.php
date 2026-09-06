<?php

namespace App\Http\Controllers\Admin\Facility\Location;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Services\Ai\RateLimitException;
use App\Services\BranchGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fill locations with AI" sweep on the facility list.
 *
 * Browser-stepped in the same shape as the SEO and English sweeps: call
 * {@see begin()} once to get the work list, then {@see step()} with a small
 * slice until it is done, so no single request has to outlive a shared-hosting
 * timeout.
 *
 * The unit of work here is a *branch*, not a facility — a facility with five
 * branches is five geocodes — so the work list is branch ids. Each one gets its
 * coordinates and its Google Maps link written and logged; the coordinates are
 * the model's best reading of the written address, which is why the dialog asks
 * the admin to check them.
 */
class AdminFacilityLocationBulkController extends BaseController
{
    use CreatorScoped;

    /** Branches handled per step — each one is an AI round trip. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(private readonly BranchGeocoder $geocoder) {}

    /**
     * Work list: which branches are missing coordinates (or, in `all` mode,
     * every branch with an address worth sending).
     */
    public function begin(Request $request): JsonResponse
    {
        $mode = $request->input('mode') === 'all' ? 'all' : 'missing';

        $branches = $this->branchQuery()->get();

        $rows = $branches
            ->filter(fn (FacilityBranch $branch) => $mode === 'all' || $this->needsLocation($branch))
            ->filter(fn (FacilityBranch $branch) => BranchGeocoder::hasEnoughContext($this->context($branch)))
            ->map(fn (FacilityBranch $branch) => [
                'id' => $branch->id,
                'label' => $this->label($branch),
            ])
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'branches' => $rows,
            'total' => $rows->count(),
            // Branches the sweep cannot help with, so the count in the dialog
            // adds up instead of silently losing rows.
            'skipped_no_address' => $branches
                ->filter(fn (FacilityBranch $branch) => $mode === 'all' || $this->needsLocation($branch))
                ->reject(fn (FacilityBranch $branch) => BranchGeocoder::hasEnoughContext($this->context($branch)))
                ->count(),
        ]);
    }

    /**
     * Geocode one slice of branches and save what comes back.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::CHUNK],
            'ids.*' => ['integer'],
            'mode' => ['nullable', 'in:missing,all'],
        ]);

        $overwrite = ($validated['mode'] ?? 'missing') === 'all';

        $branches = $this->branchQuery()
            ->whereIn('facility_branches.id', $validated['ids'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($branches as $branch) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            $result = [
                'id' => $branch->id,
                'label' => $this->label($branch),
                'state' => 'skip',
                'confidence' => null,
                'matched_place' => null,
            ];

            if (! $overwrite && ! $this->needsLocation($branch)) {
                $results[] = $result;

                continue;
            }

            try {
                $located = $this->geocoder->locate($this->context($branch));

                if ($located['latitude'] === null) {
                    // The model looked and could not place the address. That is
                    // an outcome, not a failure — say so and move on.
                    $result['state'] = 'not_found';
                    $result['confidence'] = $located['confidence'];
                } else {
                    $this->apply($branch, $located, $overwrite, $request);
                    $result['state'] = 'ok';
                    $result['confidence'] = $located['confidence'];
                    $result['matched_place'] = $located['matched_place'];
                    $result['latitude'] = $located['latitude'];
                    $result['longitude'] = $located['longitude'];
                    $result['google_location_url'] = $located['google_location_url'];
                }
            } catch (RateLimitException $e) {
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $result['state'] = 'error';
                $result['message'] = $e->getMessage();
            } catch (Throwable $e) {
                Log::error('Bulk branch geocoding failed', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);
                $result['state'] = 'error';
                $result['message'] = 'Unexpected error — see the log.';
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }

    /**
     * Branches the current admin may write, newest facility first so the sweep
     * reads in the same order as the list behind it.
     */
    private function branchQuery()
    {
        return FacilityBranch::query()
            ->with(['facility:id,name,created_by', 'governorate:id,name', 'city:id,name'])
            ->whereHas('facility', fn ($q) => $this->applyCreatorScope($q))
            ->orderBy('facility_id')
            ->orderBy('id');
    }

    /**
     * True when the branch has no coordinates yet, or has them but no link.
     */
    private function needsLocation(FacilityBranch $branch): bool
    {
        return blank($branch->latitude)
            || blank($branch->longitude)
            || blank($branch->google_location_url);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(FacilityBranch $branch): array
    {
        return [
            'facility_name' => $branch->facility?->getTranslations('name'),
            'name' => $branch->getTranslations('name'),
            'address' => $branch->getTranslations('address'),
            'governorate' => $this->placeName($branch->governorate),
            'city' => $this->placeName($branch->city),
        ];
    }

    private function placeName(mixed $place): ?string
    {
        if ($place === null) {
            return null;
        }

        return $place->getTranslation('name', 'en')
            ?: $place->getTranslation('name', 'ar')
            ?: null;
    }

    /**
     * What the dialog shows for a row: the facility, then the branch.
     */
    private function label(FacilityBranch $branch): string
    {
        $locale = app()->getLocale();

        $facility = $branch->facility?->getTranslation('name', $locale)
            ?: $branch->facility?->getTranslation('name', 'ar')
            ?: '—';

        $name = $branch->getTranslation('name', $locale)
            ?: $branch->getTranslation('name', 'ar')
            ?: $branch->getTranslation('address', $locale)
            ?: '—';

        return trim($facility.' — '.$name);
    }

    /**
     * Write the located coordinates and link, and log the change against the
     * branch the same way an edit from the form would.
     *
     * In "missing" mode each field is only filled when it is empty, so a sweep
     * cannot overwrite a coordinate somebody set by hand on the map.
     *
     * @param  array{latitude: float|null, longitude: float|null, google_location_url: string|null}  $located
     */
    private function apply(FacilityBranch $branch, array $located, bool $overwrite, Request $request): void
    {
        $before = [
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_location_url' => $branch->google_location_url,
        ];

        $hadCoordinates = filled($branch->latitude) && filled($branch->longitude);

        if ($overwrite || ! $hadCoordinates) {
            $branch->latitude = $located['latitude'];
            $branch->longitude = $located['longitude'];
        }

        if ($overwrite || blank($branch->google_location_url)) {
            // Built from whatever coordinates the branch ends up with, so a link
            // kept from an earlier run can never point somewhere else.
            $branch->google_location_url = BranchGeocoder::mapsUrl($branch->latitude, $branch->longitude);
        }

        if (! $branch->isDirty(['latitude', 'longitude', 'google_location_url'])) {
            return;
        }

        // saveQuietly: saving normally regenerates the slug from the facility
        // name, and a location fill has no business renaming a branch.
        $branch->saveQuietly();

        FacilityBranchLog::record(
            facilityBranchId: $branch->id,
            facilityId: $branch->facility_id,
            adminId: Auth::id(),
            action: FacilityBranchLog::ACTION_UPDATED,
            oldValues: $before,
            newValues: [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'google_location_url' => $branch->google_location_url,
                'source' => 'ai_geocode',
            ],
            request: $request,
        );
    }
}
