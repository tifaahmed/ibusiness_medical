<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\FacilityResource;
use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function show(Request $request, Facility $facility): JsonResponse
    {
        $facility->load(['facilityType', 'governorate', 'city', 'media', 'tags']);

        $locale = app()->getLocale();
        $branchSearch = $request->input('branch_search', '');

        $branches = FacilityBranch::with(['governorate', 'city'])
            ->where('facility_id', $facility->id)
            ->when($branchSearch, function ($q) use ($branchSearch, $locale) {
                $q->where(function ($query) use ($branchSearch, $locale) {
                    $query->where('name->' . $locale, 'like', '%' . $branchSearch . '%')
                        ->orWhere('address->' . $locale, 'like', '%' . $branchSearch . '%');
                });
            })
            ->get()
            ->map(fn($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'governorate' => $branch->governorate ? ['name' => $branch->governorate->name] : null,
                'city' => $branch->city ? ['name' => $branch->city->name] : null,
            ]);

        $facilityName = $facility->getTranslation('name', $locale);

        $sameNamePool = Facility::with(['facilityType'])
            ->where('name->' . $locale, $facilityName)
            ->where('id', '!=', $facility->id)
            ->get();

        if ($sameNamePool->isEmpty()) {
            $words = collect(explode(' ', $facilityName))
                ->map(fn($w) => trim($w))
                ->filter(fn($w) => mb_strlen($w) > 2)
                ->unique()->values();

            if ($words->isNotEmpty()) {
                $sameNamePool = Facility::with(['facilityType'])
                    ->where('id', '!=', $facility->id)
                    ->where(function ($q) use ($words, $locale) {
                        foreach ($words as $word) {
                            $q->orWhere('name->' . $locale, 'like', '%' . $word . '%');
                        }
                    })
                    ->get();
            }
        }

        $onePerGov = $sameNamePool->groupBy('governorate_id')->map->first()->values();
        if ($onePerGov->count() < 6) {
            $usedIds = $onePerGov->pluck('id');
            $extras = $sameNamePool->whereNotIn('id', $usedIds)->take(6 - $onePerGov->count());
            $sameNamePool = $onePerGov->concat($extras);
        } else {
            $sameNamePool = $onePerGov->take(6);
        }

        $sameName = $sameNamePool->map(fn($f) => [
            'id' => $f->id,
            'slug' => $f->slug,
            'name' => $f->name,
            'facility_type' => $f->facilityType ? ['name' => $f->facilityType->name] : null,
        ]);

        $sameCatPool = Facility::with(['facilityType'])
            ->where('facility_type_id', $facility->facility_type_id)
            ->where('governorate_id', $facility->governorate_id)
            ->where('id', '!=', $facility->id)
            ->limit(6)->get();

        if ($sameCatPool->count() < 6) {
            $usedIds = $sameCatPool->pluck('id')->push($facility->id);
            $extras = Facility::with(['facilityType'])
                ->where('facility_type_id', $facility->facility_type_id)
                ->where('governorate_id', '!=', $facility->governorate_id)
                ->whereNotIn('id', $usedIds)
                ->limit(6 - $sameCatPool->count())->get();
            $sameCatPool = $sameCatPool->concat($extras);
        }

        $sameCategory = $sameCatPool->map(fn($f) => [
            'id' => $f->id,
            'slug' => $f->slug,
            'name' => $f->name,
            'facility_type' => $f->facilityType ? ['name' => $f->facilityType->name] : null,
        ]);

        return response()->json([
            'facility' => new FacilityResource($facility),
            'branches' => $branches,
            'branch_search' => $branchSearch,
            'same_name' => $sameName,
            'same_category' => $sameCategory,
        ]);
    }
}
