<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Bulk;

use App\Http\Controllers\Concerns\SweepsFacilityBranches;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Services\Ai\RateLimitException;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fix translations with AI" sweep on the branch list.
 *
 * Walks every branch whose name or address is missing or wrong in Arabic or
 * English and fixes both languages of the field together, so the pair ends up
 * consistent rather than one side patched against a broken other.
 *
 * Name and address go through the one rule in
 * {@see FacilityEnglishBackfiller::languagesNeedFix()}: empty on either side,
 * in the wrong language (Arabic in the English box, Latin in the Arabic one),
 * or the same text copied into both. Address is never held to a looser rule than
 * name. A branch with nothing in either language of a field has nothing to
 * translate from, so that field is left alone.
 *
 * Only what is wrong is written, so unlike the AI sweeps beside it there is no
 * "redo everything" switch: a translation that is already right has no business
 * being regenerated. Every write is logged against the branch with its source.
 */
class AdminFacilityBranchTranslateBulkController extends BaseController
{
    use SweepsFacilityBranches;

    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    /**
     * Work list: the branches with a name or address to fix.
     */
    public function begin(Request $request): JsonResponse
    {
        $rows = $this->branchQuery()
            ->get()
            ->filter(fn (FacilityBranch $b) => $this->backfiller->branchNeedsTranslation($b))
            ->map(fn (FacilityBranch $b) => ['id' => $b->id, 'label' => $this->label($b)])
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'branches' => $rows,
            'total' => $rows->count(),
            'skipped_no_address' => 0,
        ]);
    }

    /**
     * Fix one slice of branches and save what comes back.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::CHUNK],
            'ids.*' => ['integer'],
        ]);

        $branches = $this->branchQuery()
            ->with('facility.facilityType')
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
                'detail' => null,
                'address' => $this->addressText($branch),
            ];

            if (! $this->backfiller->branchNeedsTranslation($branch)) {
                $results[] = $result;

                continue;
            }

            $before = [
                'name' => $branch->getTranslations('name'),
                'address' => $branch->getTranslations('address'),
            ];

            try {
                $fixed = $this->backfiller->fixBranch($branch);

                if ($fixed['applied'] !== []) {
                    $this->logChange($branch, $before, [
                        'name' => $branch->getTranslations('name'),
                        'address' => $branch->getTranslations('address'),
                    ], 'ai_translate', $request);

                    $result['state'] = 'ok';
                    $result['detail'] = implode(' + ', array_column($fixed['applied'], 'field'));
                    // What the dialog shows under the row is what it now says.
                    $result['address'] = $this->addressText($branch);
                } elseif ($fixed['errors'] !== []) {
                    $result['state'] = 'error';
                    $result['message'] = implode(' ', $fixed['errors']);
                }
            } catch (RateLimitException $e) {
                // The slice is left unprocessed; the browser waits and re-sends it.
                $rateLimited = true;
                break;
            } catch (RuntimeException $e) {
                Log::warning('Bulk branch translation refused', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);
                $result['state'] = 'error';
                $result['message'] = $e->getMessage();
            } catch (Throwable $e) {
                Log::error('Bulk branch translation failed', [
                    'route' => $request->path(),
                    'branch_id' => $branch->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $result['state'] = 'error';
                $result['message'] = 'Unexpected error — see the log.';
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }
}
