<?php

namespace App\Http\Controllers\Admin\Governorate\English;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Governorate\TranslateGovernorateRequest;
use App\Services\GovernorateEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs "Fix English fields with AI" on the governorate create page.
 *
 * The edit page's button reads the saved governorate and writes the
 * corrected English straight back; on create there is no row yet, so this one
 * works on the name as typed into the form and writes nothing. The English
 * value goes back into the open form for the admin to check before saving.
 */
class AdminGovernorateTranslateController extends BaseController
{
    public function __construct(private readonly GovernorateEnglishBackfiller $backfiller) {}

    public function __invoke(TranslateGovernorateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $ar = (string) data_get($validated, 'name.ar', '');
        $en = (string) data_get($validated, 'name.en', '');

        if (! $this->backfiller->needsFix($en, $ar)) {
            return response()->json(['name' => null, 'filled' => 0]);
        }

        try {
            $value = $this->backfiller->translateName($ar);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($value === null) {
            return response()->json([
                'message' => 'The AI could not produce English for this name. Try again, or fill it in by hand.',
            ], 422);
        }

        return response()->json(['name' => $value, 'filled' => 1]);
    }
}
