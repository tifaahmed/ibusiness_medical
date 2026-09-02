<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Facilities\SearchDirectoryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The directory's suggestion box: one phrase in, everything it could mean out.
 *
 * Separate from the listing endpoint on purpose. That one answers "show me a
 * page of results" and ships the filter options and the offers with it; this
 * one answers "what could this be?" while somebody is still typing, so it is
 * small, grouped by kind, and asked several times a second.
 *
 * Public and key-less like the rest of the guest API — a directory of partner
 * clinics is a shop window, and nothing here is about a member.
 */
class FacilitySearchController extends Controller
{
    public function __construct(private SearchDirectoryAction $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_group' => ['nullable', 'integer', 'min:1', 'max:'.SearchDirectoryAction::MAX_PER_GROUP],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        try {
            $results = $this->search->handle(
                $term,
                (int) ($validated['per_group'] ?? SearchDirectoryAction::PER_GROUP),
            );
        } catch (Throwable $exception) {
            Log::error('Directory search failed.', [
                'term' => $term,
                'locale' => app()->getLocale(),
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The directory could not be searched.',
            ], 500);
        }

        return response()->json($results)
            /*
             * Cacheable, but only briefly and only per URL: the answer is the
             * same for everybody asking the same thing, and a minute is long
             * enough to absorb a burst of keystrokes without a newly added
             * facility staying invisible for an afternoon.
             */
            ->header('Cache-Control', 'public, max-age=60');
    }
}
