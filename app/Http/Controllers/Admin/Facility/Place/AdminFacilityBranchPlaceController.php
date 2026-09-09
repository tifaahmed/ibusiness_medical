<?php

namespace App\Http\Controllers\Admin\Facility\Place;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\ResolveFacilityBranchPlaceRequest;
use App\Models\City;
use App\Models\Governorate;
use App\Services\BranchPlaceResolver;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Fill governorate & city from the address" button on the branch form.
 *
 * Answers the ids of rows that exist, plus their names, so the form can both
 * select them and say out loud which place was chosen — the admin is meant to
 * read that back against the address before saving.
 */
class AdminFacilityBranchPlaceController extends BaseController
{
    public function __construct(private readonly BranchPlaceResolver $resolver) {}

    public function __invoke(ResolveFacilityBranchPlaceRequest $request): JsonResponse
    {
        $context = $request->validated();

        if (! BranchPlaceResolver::hasEnoughContext($context)) {
            return response()->json([
                'message' => 'Enter the branch address first — there is nothing to read the place from.',
            ], 422);
        }

        try {
            $place = $this->resolver->resolve($context);
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are both the admin's to
            // act on, so surface the message instead of a bare 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($place['governorate_id'] === null && $place['city_id'] === null) {
            return response()->json([
                'message' => 'The AI could not tell which governorate or city this address is in. Please choose them by hand.',
            ], 422);
        }

        // Both spellings of each name, so the form can say which place was
        // chosen in the reader's own language. Read through the model rather
        // than value('name'), which hands back the raw JSON uncast.
        return response()->json([
            'place' => [
                ...$place,
                'governorate_name' => Governorate::find($place['governorate_id'])?->getTranslations('name'),
                'city_name' => City::find($place['city_id'])?->getTranslations('name'),
            ],
        ]);
    }
}
