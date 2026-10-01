<?php

namespace App\Http\Controllers\Admin\Facility\Banner;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\SuggestFacilityBannerRequest;
use App\Services\FacilityBannerSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Backs the "Suggest with AI" button on the facility banner card: a short
 * Arabic/English ribbon message and its colours, fitted to the facility. Works
 * on the form as it stands (create and edit alike) and writes nothing — the
 * suggestion goes into the open form for the admin to check in the preview.
 */
class AdminFacilityBannerSuggestController extends BaseController
{
    public function __construct(private readonly FacilityBannerSuggester $suggester) {}

    public function __invoke(SuggestFacilityBannerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $plain = fn (?string $html) => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))) ?? ''), 0, 1500);
        $discount = $validated['discount_percent'] ?? null;

        try {
            $values = $this->suggester->suggest([
                'Facility (Arabic)' => (string) data_get($validated, 'name.ar', ''),
                'Facility (English)' => (string) data_get($validated, 'name.en', ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
                'Discount for members' => $discount !== null && (float) $discount > 0 ? rtrim(rtrim((string) $discount, '0'), '.').'%' : '',
                'Description (Arabic)' => $plain(data_get($validated, 'description.ar')),
                'Description (English)' => $plain(data_get($validated, 'description.en')),
            ], (array) ($validated['current'] ?? []));
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are the admin's to act on.
            Log::warning('Facility banner suggestion failed', [
                'route' => $request->route()?->getName(),
                'facility' => data_get($validated, 'name.ar') ?: data_get($validated, 'name.en'),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($values === null) {
            return response()->json([
                'message' => 'The AI could not suggest a usable banner this time. Please try again.',
            ], 422);
        }

        return response()->json(['values' => $values]);
    }
}
