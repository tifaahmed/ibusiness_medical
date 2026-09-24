<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Stores\ClusterStoreBranches;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every store branch on the map, at once — merged by proximity, mirrors
 * `FacilityBranchMapController` exactly. Public and key-less like the rest of
 * the guest API. Read by the Deilar storefront's "view all branches" map on
 * the stores directory.
 */
class StoreBranchMapController extends Controller
{
    public function __construct(private ClusterStoreBranches $cluster) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zoom' => ['nullable', 'integer', 'min:1', 'max:19'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        try {
            $clusters = $this->cluster->handle(
                zoom: (int) ($validated['zoom'] ?? 6),
                governorateId: $validated['governorate_id'] ?? null,
                cityId: $validated['city_id'] ?? null,
            );
        } catch (Throwable $exception) {
            Log::error('Store branch map clustering failed.', [
                'zoom' => $validated['zoom'] ?? null,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['clusters' => []], 500);
        }

        return response()->json(['clusters' => $clusters])
            ->header('Cache-Control', 'public, max-age=120');
    }
}
