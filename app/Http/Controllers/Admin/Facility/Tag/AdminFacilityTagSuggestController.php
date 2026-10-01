<?php

namespace App\Http\Controllers\Admin\Facility\Tag;

use App\Http\Controllers\Controller as BaseController;
use App\Services\FacilityTagSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Backs "Pick tags with AI" on the facility form: which existing tags suit the
 * facility as typed, or — when none does — a new tag to prefill into the
 * quick-add popup. Works on the open form and writes nothing.
 */
class AdminFacilityTagSuggestController extends BaseController
{
    public function __construct(private readonly FacilityTagSuggester $suggester) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'facility_type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:30000'],
            'description.en' => ['nullable', 'string', 'max:30000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        $plain = fn (?string $html) => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))) ?? ''), 0, 2000);

        try {
            $result = $this->suggester->suggest([
                'Facility (Arabic)' => (string) data_get($validated, 'name.ar', ''),
                'Facility (English)' => (string) data_get($validated, 'name.en', ''),
                'Facility type' => (string) ($validated['facility_type'] ?? ''),
                'Description (Arabic)' => $plain(data_get($validated, 'description.ar')),
                'Description (English)' => $plain(data_get($validated, 'description.en')),
            ], $validated['tag_ids'] ?? []);
        } catch (RuntimeException $e) {
            Log::warning('Facility tag suggestion failed', [
                'route' => $request->route()?->getName(),
                'facility' => data_get($validated, 'name.ar') ?: data_get($validated, 'name.en'),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
