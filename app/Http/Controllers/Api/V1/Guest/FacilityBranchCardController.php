<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One branch's popup card on the storefront's "all branches" map: the
 * facility's logo, name, address, city, governorate and every phone number.
 *
 * Fetched on the click rather than shipped with every pin — the map draws
 * hundreds of pins and only one popup is ever open. Public and key-less like
 * the rest of the guest API; a soft-deleted branch is a 404 by default.
 */
class FacilityBranchCardController extends Controller
{
    public function __invoke(int $branch): JsonResponse
    {
        try {
            $model = FacilityBranch::with(['facility', 'city', 'governorate'])->findOrFail($branch);

            return response()->json(['branch' => [
                'id' => $model->id,
                'name' => $model->name,
                'address' => $model->address,
                'city' => $model->city?->name,
                'governorate' => $model->governorate?->name,
                'phones' => $model->phoneNumbers(),
                'image' => $model->facility?->logo ?: null,
                'facility' => $model->facility ? [
                    'slug' => $model->facility->slug,
                    'name' => $model->facility->name,
                ] : null,
            ]]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['branch' => null], 404);
        } catch (Throwable $exception) {
            Log::error('Branch card failed.', [
                'route' => 'facilities.branch-card',
                'branch_id' => $branch,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['branch' => null], 500);
        }
    }
}
