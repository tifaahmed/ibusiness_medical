<?php

namespace App\Http\Controllers\Admin\Facility\English;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\TranslateFacilityBranchRequest;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Fix English with AI" button in the facility form's branch modal.
 *
 * The facility-wide button next to it works on saved rows; this one works on
 * the boxes as they stand, so it also helps on the create page and on a branch
 * that has been typed but not saved. It writes nothing — the English values go
 * into the open form for the admin to check, as with "Find on map with AI".
 */
class AdminFacilityBranchTranslateController extends BaseController
{
    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    public function __invoke(TranslateFacilityBranchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fields = [];
        if (trim((string) data_get($validated, 'name.ar')) !== '') {
            $fields['name'] = ['kind' => 'branch name', 'ar' => data_get($validated, 'name.ar')];
        }
        if (trim((string) data_get($validated, 'address.ar')) !== '') {
            $fields['address'] = ['kind' => 'address', 'ar' => data_get($validated, 'address.ar')];
        }

        if ($fields === []) {
            return response()->json([
                'message' => 'Fill in the Arabic name or address first — the AI translates from the Arabic side.',
            ], 422);
        }

        try {
            $values = $this->backfiller->translateFields($fields, [
                'Facility (for context)' => (string) ($validated['facility_name'] ?? ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
                'Governorate' => (string) ($validated['governorate'] ?? ''),
                'City' => (string) ($validated['city'] ?? ''),
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

        return response()->json(['values' => $values]);
    }
}
