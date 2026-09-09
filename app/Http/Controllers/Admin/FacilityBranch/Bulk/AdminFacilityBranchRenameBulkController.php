<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Bulk;

use App\Http\Controllers\Concerns\SweepsFacilityBranches;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Support\BranchNamer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The "Fix branch names" sweep on the branch list.
 *
 * Renames branches to the house style — "<facility> - <city>", numbered when a
 * facility has more than one branch in the same city — so no two branches of
 * one facility share a name. That is the rule {@see \App\Support\BranchUniqueness}
 * enforces on save, which is why a branch that has drifted into a duplicate
 * cannot be edited at all until its name is fixed: this clears that backlog.
 *
 * Unlike the AI sweeps beside it this calls nothing external; it is browser-
 * stepped all the same, because a step still writes rows and the progress and
 * the per-facility report are worth having. The unit of work is a *facility*,
 * not a branch: the numbering can only be decided with the whole facility in
 * hand.
 *
 * Slugs are deliberately left alone — see {@see rename()}.
 */
class AdminFacilityBranchRenameBulkController extends BaseController
{
    use SweepsFacilityBranches;

    /** Facilities per step. No AI call here, so this can be far larger. */
    private const FACILITY_CHUNK = 25;

    /**
     * Work list: every facility that has a branch this admin may rename.
     */
    public function begin(Request $request): JsonResponse
    {
        $facilityIds = $this->branchQuery()
            ->reorder()
            ->distinct()
            ->pluck('facility_id')
            ->filter()
            ->values();

        $facilities = Facility::whereIn('id', $facilityIds)
            ->orderBy('id')
            ->get(['id', 'name']);

        $rows = $facilities->map(fn (Facility $facility) => [
            'id' => $facility->id,
            'label' => $this->facilityLabel($facility),
        ])->values();

        return response()->json([
            'chunk' => self::FACILITY_CHUNK,
            'branches' => $rows,
            'total' => $rows->count(),
            'skipped_no_address' => 0,
        ]);
    }

    /**
     * Rename one slice of facilities.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::FACILITY_CHUNK],
            'ids.*' => ['integer'],
            'mode' => ['nullable', 'in:missing,all'],
        ]);

        // "all" is the default here, unlike the AI sweeps: the point of the
        // button is to put every branch into the house style. "missing" is the
        // careful option — fix only the duplicates and the blanks.
        $onlyProblems = ($validated['mode'] ?? 'all') === 'missing';

        $facilities = Facility::whereIn('id', $validated['ids'])->orderBy('id')->get();

        $results = [];

        foreach ($facilities as $facility) {
            $result = [
                'id' => $facility->id,
                'label' => $this->facilityLabel($facility),
                'state' => 'skip',
                'confidence' => null,
                'detail' => null,
            ];

            try {
                $renamed = $this->rename($facility, $onlyProblems, $request);

                if ($renamed > 0) {
                    $result['state'] = 'ok';
                    $result['detail'] = $renamed.' '.($renamed === 1 ? 'branch renamed' : 'branches renamed');
                }
            } catch (Throwable $e) {
                Log::error('Bulk branch rename failed', ['facility_id' => $facility->id, 'error' => $e->getMessage()]);
                $result['state'] = 'error';
                $result['message'] = 'Unexpected error — see the log.';
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => false]);
    }

    /**
     * Rename the branches of one facility, and report how many changed.
     *
     * Every branch of the facility is loaded, not only the ones this admin may
     * write: a name has to be checked against all of them or the sweep could
     * hand a scoped admin a name that collides with a branch they cannot see.
     * The ones they may not write are reserved and left as they are.
     */
    private function rename(Facility $facility, bool $onlyProblems, Request $request): int
    {
        $branches = FacilityBranch::query()
            ->where('facility_id', $facility->id)
            ->with('city:id,name')
            ->orderBy('id')
            ->get();

        if ($branches->isEmpty()) {
            return 0;
        }

        $writableIds = $this->branchQuery()
            ->reorder()
            ->where('facility_id', $facility->id)
            ->pluck('facility_branches.id')
            ->all();

        $changes = BranchNamer::nameBranches($facility, $branches, $writableIds, $onlyProblems);

        $renamed = 0;

        foreach ($branches as $branch) {
            if (! isset($changes[$branch->id])) {
                continue;
            }

            $before = ['name' => $branch->getTranslations('name')];

            $branch->setTranslations('name', $changes[$branch->id]);

            if (! $branch->isDirty('name')) {
                continue;
            }

            /* saveQuietly: the slug is generated from the name, so a normal save
               would rewrite the slug of every branch on the site and break every
               link and bookmark already pointing at one. A tidier name is not
               worth that; the slug stays as the address it has always been. */
            $branch->saveQuietly();

            $this->logChange($branch, $before, ['name' => $branch->getTranslations('name')], 'rename_sweep', $request);

            $renamed++;
        }

        return $renamed;
    }

    private function facilityLabel(Facility $facility): string
    {
        return $facility->getTranslation('name', app()->getLocale())
            ?: $facility->getTranslation('name', 'ar')
            ?: $facility->getTranslation('name', 'en')
            ?: '—';
    }
}
