<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Languages;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\FacilityBranch\FixBranchLanguagesRequest;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Fix languages with AI" button on the branch form.
 *
 * Unlike the facility form's English-only button, a problem on either side
 * (empty, wrong language, swapped, copied) has both the Arabic and the English
 * rewritten together, so the pair ends up consistent. Name and address go
 * through the identical check. It writes nothing — the corrected values go
 * into the open form for the admin to check before saving.
 */
class AdminFacilityBranchFixLanguagesController extends BaseController
{
    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    public function __invoke(FixBranchLanguagesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fields = [];
        foreach (['name' => 'branch name', 'address' => 'address'] as $field => $kind) {
            $ar = trim((string) data_get($validated, "{$field}.ar"));
            $en = trim((string) data_get($validated, "{$field}.en"));

            if ($ar !== '' || $en !== '') {
                $fields[$field] = ['kind' => $kind, 'ar' => $ar, 'en' => $en];
            }
        }

        if ($fields === []) {
            return response()->json([
                'message' => 'Fill in the name or address first — there is nothing to fix yet.',
            ], 422);
        }

        // Everything typed already reads correctly in both languages.
        $needsFix = array_filter($fields, fn ($f) => $this->backfiller->languagesNeedFix($f['ar'], $f['en']));
        if ($needsFix === []) {
            return response()->json(['values' => [], 'message' => 'Both languages already look right.']);
        }

        try {
            $values = $this->backfiller->fixLanguages($fields, [
                'Facility (for context)' => (string) ($validated['facility_name'] ?? ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
                'Governorate' => (string) ($validated['governorate'] ?? ''),
                'City' => (string) ($validated['city'] ?? ''),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($values === []) {
            return response()->json([
                'message' => 'The AI could not fix these fields. Try again, or fill them in by hand.',
            ], 422);
        }

        return response()->json(['values' => $values]);
    }
}
