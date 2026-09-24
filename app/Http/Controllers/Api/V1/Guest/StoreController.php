<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Guest\StoreResource;
use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One store, and its branches — mirrors `FacilityController::show()` but
 * without the franchise-dedup "same name" / "same category" lists, which are
 * specific to how Facility handles chains sharing a name.
 */
class StoreController extends Controller
{
    public function show(Request $request, Store $store): JsonResponse
    {
        $store->load(['media']);

        $locale = app()->getLocale();
        $branchSearch = $request->input('branch_search', '');

        $branches = StoreBranch::with(['governorate', 'city'])
            ->where('store_id', $store->id)
            ->when($branchSearch, function ($q) use ($branchSearch, $locale) {
                $q->where(function ($query) use ($branchSearch, $locale) {
                    $query->where('name->'.$locale, 'like', '%'.$branchSearch.'%')
                        ->orWhere('address->'.$locale, 'like', '%'.$branchSearch.'%');
                });
            })
            ->get()
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'area' => $branch->area,
                'phone' => $branch->phoneNumbers(),
                'phones' => $branch->phone,
                'governorate' => $branch->governorate ? ['id' => $branch->governorate->id, 'name' => $branch->governorate->name] : null,
                'city' => $branch->city ? ['id' => $branch->city->id, 'name' => $branch->city->name] : null,
                'latitude' => $branch->latitude !== null ? (float) $branch->latitude : null,
                'longitude' => $branch->longitude !== null ? (float) $branch->longitude : null,
                'google_location_url' => $branch->google_location_url,
            ]);

        return response()->json([
            'store' => new StoreResource($store),
            'branches' => $branches,
            'branch_search' => $branchSearch,
        ]);
    }
}
