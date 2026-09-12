<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Bulk;

use App\Http\Controllers\Concerns\SweepsFacilityBranches;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Services\Ai\RateLimitException;
use App\Services\BranchGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fill GPS with AI" sweep on the branch list.
 *
 * Walks every branch that has an address but no coordinates (or no Google Maps
 * link), reads the address, and fills them in — the same job as the button on
 * the branch form, done down the whole list.
 *
 * The facility list has a sweep of its own for this; this one is reached from
 * the branch list and is scoped by the branch permissions, so an admin who
 * manages branches but not facilities can run it over their own rows.
 *
 * The coordinates are the model's reading of a written address, not a survey —
 * which is why the dialog reports a confidence per row and links the pin for
 * checking, and why "missing" mode never touches a coordinate already set.
 */
class AdminFacilityBranchLocationBulkController extends BaseController
{
    use SweepsFacilityBranches;

    public function __construct(private readonly BranchGeocoder $geocoder) {}

    /**
     * Work list: branches with an address that have no coordinates or no map
     * link. In `all` mode, every branch with an address.
     */
    public function begin(Request $request): JsonResponse
    {
        $mode = $request->input('mode') === 'all' ? 'all' : 'missing';

        $branches = $this->branchQuery()->get();

        $wanted = $branches->filter(fn (FacilityBranch $b) => $mode === 'all' || $this->needsLocation($b));

        $rows = $wanted
            ->filter(fn (FacilityBranch $b) => $this->hasAddress($b))
            ->map(fn (FacilityBranch $b) => ['id' => $b->id, 'label' => $this->label($b)])
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'branches' => $rows,
            'total' => $rows->count(),
            'skipped_no_address' => $wanted->reject(fn (FacilityBranch $b) => $this->hasAddress($b))->count(),
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

        $branches = $this->branchQuery()->whereIn('facility_branches.id', $validated['ids'])->get();

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
                'detail' => null,
                'address' => $this->addressText($branch),
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
                    $written = $this->apply($branch, $located, $overwrite, $request);

                    $result['state'] = $written ? 'ok' : 'skip';
                    $result['confidence'] = $located['confidence'];
                    $result['detail'] = $located['matched_place']
                        ?: $branch->latitude.', '.$branch->longitude;
                    $result['url'] = $branch->google_location_url;
                }
            } catch (RateLimitException $e) {
                // The slice is left unprocessed; the browser waits and re-sends it.
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

    /** No coordinates yet, or coordinates but no link to check them against. */
    private function needsLocation(FacilityBranch $branch): bool
    {
        return blank($branch->latitude)
            || blank($branch->longitude)
            || blank($branch->google_location_url);
    }

    /**
     * Write the located coordinates and link. In "missing" mode each field is
     * only filled when it is empty, so a sweep cannot overwrite a coordinate
     * somebody checked against the map by hand.
     *
     * @param  array{latitude: float|null, longitude: float|null}  $located
     * @return bool whether anything actually changed
     */
    private function apply(FacilityBranch $branch, array $located, bool $overwrite, Request $request): bool
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
            return false;
        }

        // saveQuietly: saving normally regenerates the slug from the facility
        // name, and a location fill has no business renaming a branch.
        $branch->saveQuietly();

        $this->logChange($branch, $before, [
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_location_url' => $branch->google_location_url,
        ], 'ai_geocode', $request);

        return true;
    }
}
