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
 * Backs the translate buttons on the migration preview screen — the per-field
 * ones on either side of a name, and the sweeps that fill a whole package.
 * Called over axios with a flat list of strings and the language wanted back,
 * answers a matching list of translations.
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
            // Which side of the pair to fill. Absent means Arabic, which is what
            // the screen asked for before it could ask the other way round.
            'to' => ['nullable', 'in:ar,en'],
        ]);

        try {
            $translations = $this->translator->translate($validated['items'], $validated['to'] ?? 'ar');

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
