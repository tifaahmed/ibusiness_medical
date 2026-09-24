<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Stores\SearchStoreDirectoryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The stores directory's suggestion box — mirrors `FacilitySearchController`
 * exactly, over `SearchStoreDirectoryAction`. Public and key-less like the
 * rest of the guest API.
 */
class StoreSearchController extends Controller
{
    public function __construct(private SearchStoreDirectoryAction $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_group' => ['nullable', 'integer', 'min:1', 'max:'.SearchStoreDirectoryAction::MAX_PER_GROUP],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        try {
            $results = $this->search->handle(
                $term,
                (int) ($validated['per_group'] ?? SearchStoreDirectoryAction::PER_GROUP),
            );
        } catch (Throwable $exception) {
            Log::error('Store directory search failed.', [
                'term' => $term,
                'locale' => app()->getLocale(),
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The stores directory could not be searched.',
            ], 500);
        }

        return response()->json($results)
            ->header('Cache-Control', 'public, max-age=60');
    }
}
