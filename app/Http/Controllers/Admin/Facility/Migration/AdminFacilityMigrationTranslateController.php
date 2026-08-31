<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Services\Ai\RateLimitException;
use App\Services\FacilityMigration\MigrationTextTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Backs the "translate to Arabic" buttons on the migration preview screen —
 * both the per-field one and the "Fill Arabic from English" sweep. Called over
 * axios with a flat list of English strings, answers a matching list of Arabic.
 *
 * Nothing is written: the preview screen holds the package in the browser and
 * writes the answers back into the inputs itself.
 */
class AdminFacilityMigrationTranslateController extends BaseController
{
    public function __construct(private readonly MigrationTextTranslator $translator) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! MigrationTextTranslator::isConfigured()) {
            return response()->json(['message' => 'AI translation is not configured on this site.'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.MigrationTextTranslator::MAX_ITEMS],
            'items.*.text' => ['required', 'string', 'max:2000'],
            'items.*.kind' => ['nullable', 'in:name,address,text'],
        ]);

        try {
            $translations = $this->translator->toArabic($validated['items']);

            $payload = ['translations' => $translations];
            // When nothing usable came back, hand the raw model answer to the
            // browser too so the failure can be seen without server log access.
            if (implode('', $translations) === '') {
                $payload['debug'] = $this->translator->lastAnswer;
            }

            return response()->json($payload);
        } catch (RateLimitException $e) {
            // The sweep retries the same slice after a short countdown, exactly
            // like the SEO / English bulk tools.
            return response()->json(['rate_limited' => true, 'message' => $e->getMessage()], 429);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Facility migration translate failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The translation request failed. Please try again.'], 422);
        }
    }
}
