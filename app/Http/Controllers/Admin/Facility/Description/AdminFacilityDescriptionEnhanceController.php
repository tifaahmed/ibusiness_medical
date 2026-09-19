<?php

namespace App\Http\Controllers\Admin\Facility\Description;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\EnhanceFacilityDescriptionRequest;
use App\Services\FacilityDescriptionEnhancer;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Enhance with AI" button on the facility description: the same
 * text, laid out with short icon headings and bullet points. Works on the boxes
 * as they stand (create and edit alike) and writes nothing — the HTML goes back
 * into the open form for the admin to check before saving.
 */
class AdminFacilityDescriptionEnhanceController extends BaseController
{
    public function __construct(private readonly FacilityDescriptionEnhancer $enhancer) {}

    public function __invoke(EnhanceFacilityDescriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $values = $this->enhancer->enhance($validated['description'], [
                'Facility (Arabic)' => (string) data_get($validated, 'name.ar', ''),
                'Facility (English)' => (string) data_get($validated, 'name.en', ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
            ]);
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are the admin's to act on.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($values === []) {
            return response()->json([
                'message' => 'Write a description first, or try again — the AI could not improve it this time.',
            ], 422);
        }

        return response()->json(['values' => $values]);
    }
}
