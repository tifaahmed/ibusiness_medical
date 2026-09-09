<?php

namespace App\Http\Controllers\Admin\Facility\English;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\TranslateFacilityRequest;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs "Fix English fields with AI" on the facility create page.
 *
 * The edit page's button reads the saved facility and writes the corrected
 * English straight back; on create there is no row yet, so this one works on
 * the boxes as they stand — the facility's own fields plus every branch typed
 * into the form — and writes nothing. The English values go back into the open
 * form for the admin to check before saving, as with "Find on map with AI".
 */
class AdminFacilityTranslateController extends BaseController
{
    /** The facility's own prose, and the branch fields, worth translating. */
    private const FACILITY_FIELDS = ['name' => 'facility name', 'description' => 'facility description'];

    private const BRANCH_FIELDS = ['name' => 'branch name', 'address' => 'address'];

    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    public function __invoke(TranslateFacilityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // One flat map for the AI, keyed so the answers can be put back where
        // they came from: 'name' for the facility, 'branches.2.address' for a
        // branch. Only the fields that actually need a fix are sent — the same
        // test the saved-row sweep uses, so both buttons agree on what "needs
        // English" means.
        $fields = [];

        foreach (self::FACILITY_FIELDS as $field => $kind) {
            $ar = (string) data_get($validated, "{$field}.ar", '');
            $en = (string) data_get($validated, "{$field}.en", '');

            if ($this->backfiller->needsFix($en, $ar)) {
                $fields[$field] = ['kind' => $kind, 'ar' => $ar];
            }
        }

        foreach ($validated['branches'] ?? [] as $index => $branch) {
            // The place a branch sits in is what tells the AI whether a name is
            // a district or a person, so it rides along with the field itself —
            // the shared context block can only describe the facility.
            $place = collect([data_get($branch, 'city'), data_get($branch, 'governorate')])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->implode(', ');

            foreach (self::BRANCH_FIELDS as $field => $kind) {
                $ar = (string) data_get($branch, "{$field}.ar", '');
                $en = (string) data_get($branch, "{$field}.en", '');

                if (! $this->backfiller->needsFix($en, $ar)) {
                    continue;
                }

                $fields["branches.{$index}.{$field}"] = [
                    'kind' => $place === '' ? $kind : "{$kind} in {$place}",
                    'ar' => $ar,
                ];
            }
        }

        if ($fields === []) {
            return response()->json(['values' => [], 'branches' => [], 'filled' => 0]);
        }

        try {
            $values = $this->backfiller->translateFields($fields, [
                'Facility' => (string) data_get($validated, 'name.ar', ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
            ]);
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are both the admin's to
            // act on, so surface the message instead of a bare 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($values === []) {
            return response()->json([
                'message' => 'The AI could not produce English for these fields. Try again, or fill them in by hand.',
            ], 422);
        }

        $facility = [];
        $branches = [];

        foreach ($values as $key => $value) {
            if (preg_match('/^branches\.(\d+)\.(\w+)$/', $key, $m)) {
                $branches[$m[1]][$m[2]] = $value;

                continue;
            }

            $facility[$key] = $value;
        }

        return response()->json([
            'values' => $facility,
            'branches' => $branches,
            'filled' => count($values),
        ]);
    }
}
