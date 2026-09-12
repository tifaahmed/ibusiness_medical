<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Bulk;

use App\Http\Controllers\Concerns\SweepsFacilityBranches;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Services\Ai\RateLimitException;
use App\Services\BranchPlaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fill governorate & city with AI" sweep on the branch list.
 *
 * Walks every branch that has an address but is missing its governorate or its
 * city, reads the address, and fills in the two fields — the same job as the
 * button on the branch form, done down the whole list.
 *
 * Those two fields are what every place filter on the site reads, and a branch
 * without them is invisible to all of them; they are also required to save a
 * branch, so a row missing one cannot be edited until somebody supplies it.
 * That is the backlog this clears.
 *
 * The model only ever picks from the governorates and cities that exist — see
 * {@see BranchPlaceResolver} — so a sweep cannot invent a place. What it can do
 * is choose the wrong real one, which is why every write is logged against the
 * branch with its source, and the dialog lists what it chose per row.
 */
class AdminFacilityBranchPlaceBulkController extends BaseController
{
    use SweepsFacilityBranches;

    public function __construct(private readonly BranchPlaceResolver $resolver) {}

    /**
     * Work list: branches with an address that are missing a governorate or a
     * city. In `all` mode, every branch with an address.
     */
    public function begin(Request $request): JsonResponse
    {
        $mode = $request->input('mode') === 'all' ? 'all' : 'missing';

        $branches = $this->branchQuery()->get();

        $wanted = $branches->filter(fn (FacilityBranch $b) => $mode === 'all' || $this->needsPlace($b));

        $rows = $wanted
            ->filter(fn (FacilityBranch $b) => $this->hasAddress($b))
            ->map(fn (FacilityBranch $b) => ['id' => $b->id, 'label' => $this->label($b)])
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'branches' => $rows,
            'total' => $rows->count(),
            // The rows this sweep cannot help with, so the count in the dialog
            // adds up instead of silently losing branches.
            'skipped_no_address' => $wanted->reject(fn (FacilityBranch $b) => $this->hasAddress($b))->count(),
        ]);
    }

    /**
     * Resolve one slice of branches and save what comes back.
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

            if (! $overwrite && ! $this->needsPlace($branch)) {
                $results[] = $result;

                continue;
            }

            try {
                $place = $this->resolver->resolve($this->context($branch));

                if ($place['governorate_id'] === null && $place['city_id'] === null) {
                    // The model read the address and could not place it. That is
                    // an outcome, not a failure — say so and move on.
                    $result['state'] = 'not_found';
                    $result['confidence'] = $place['confidence'];
                } else {
                    $written = $this->apply($branch, $place, $overwrite, $request);

                    $result['state'] = $written ? 'ok' : 'skip';
                    $result['confidence'] = $place['confidence'];
                    $result['detail'] = $this->chosen($branch);
                }
            } catch (RateLimitException $e) {
                // The slice is left unprocessed; the browser waits and re-sends it.
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                $result['state'] = 'error';
                $result['message'] = $e->getMessage();
            } catch (Throwable $e) {
                Log::error('Bulk branch place fill failed', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);
                $result['state'] = 'error';
                $result['message'] = 'Unexpected error — see the log.';
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }

    /** A branch is worth reading when either field is still empty. */
    private function needsPlace(FacilityBranch $branch): bool
    {
        return blank($branch->governorate_id) || blank($branch->city_id);
    }

    /**
     * Write the chosen place. In "missing" mode each field is only filled when
     * it is empty, so a sweep can never overwrite a place somebody set by hand.
     *
     * @param  array{governorate_id: int|null, city_id: int|null}  $place
     * @return bool whether anything actually changed
     */
    private function apply(FacilityBranch $branch, array $place, bool $overwrite, Request $request): bool
    {
        $before = [
            'governorate_id' => $branch->governorate_id,
            'city_id' => $branch->city_id,
        ];

        if ($place['governorate_id'] !== null && ($overwrite || blank($branch->governorate_id))) {
            $branch->governorate_id = $place['governorate_id'];
        }

        if ($place['city_id'] !== null && ($overwrite || blank($branch->city_id))) {
            $branch->city_id = $place['city_id'];
        }

        if (! $branch->isDirty(['governorate_id', 'city_id'])) {
            return false;
        }

        // saveQuietly: saving normally regenerates the slug from the facility
        // name, and filling in a place has no business renaming a branch.
        $branch->saveQuietly();
        $branch->load(['governorate:id,name', 'city:id,name']);

        $this->logChange($branch, $before, [
            'governorate_id' => $branch->governorate_id,
            'city_id' => $branch->city_id,
        ], 'ai_place', $request);

        return true;
    }

    /** "Governorate — City" as the branch now stands, for the dialog's row. */
    private function chosen(FacilityBranch $branch): ?string
    {
        $parts = array_filter([
            $this->placeName($branch->governorate),
            $this->placeName($branch->city),
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }
}
