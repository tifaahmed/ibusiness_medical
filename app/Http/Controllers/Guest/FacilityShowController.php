<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FacilityShowController extends BaseController
{
    public function __invoke(Request $request, Facility $facility): Response
    {
        $facility->load(['facilityType', 'media']);

        $locale = app()->getLocale();
        $branchSearch = $request->input('branch_search', '');

        $branches = FacilityBranch::with(['governorate', 'city'])->where('facility_id', $facility->id)
            ->when($branchSearch, function ($q) use ($branchSearch, $locale) {
                $q->where(function ($query) use ($branchSearch, $locale) {
                    $query->where('name->' . $locale, 'like', '%' . $branchSearch . '%')
                          ->orWhere('address->' . $locale, 'like', '%' . $branchSearch . '%');
                });
            })
            ->get()
            ->map(function ($branch) {
                return [
                    'id'             => $branch->id,
                    'name'           => $branch->name,
                    'address'        => $branch->address,
                    'phone'          => $branch->phone,
                    'governorate_id' => $branch->governorate_id,
                    'governorate'    => $branch->governorate?->name,
                    'city_id'        => $branch->city_id,
                    'city'           => $branch->city?->name,
                ];
            });

        $facilityName = $facility->getTranslation('name', $locale);

        // ── Same Name ────────────────────────────────────────────────────────────
        // 1. Exact match (all results, no limit yet — we need them for grouping)
        $sameNamePool = Facility::with(['facilityType'])
            ->where('name->' . $locale, $facilityName)
            ->where('id', '!=', $facility->id)
            ->get();

        // 2. Fall back to word-similarity when no exact matches
        if ($sameNamePool->isEmpty()) {
            $words = collect(explode(' ', $facilityName))
                ->map(fn($w) => trim($w))
                ->filter(fn($w) => mb_strlen($w) > 2)
                ->unique()
                ->values();

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

        // 3. One per governorate first, then fill duplicates to reach 6
        $onePerGov = $sameNamePool->groupBy('governorate_id')->map->first()->values();
        if ($onePerGov->count() < 6) {
            $usedIds   = $onePerGov->pluck('id');
            $extras    = $sameNamePool->whereNotIn('id', $usedIds)->take(6 - $onePerGov->count());
            $sameNamePool = $onePerGov->concat($extras);
        } else {
            $sameNamePool = $onePerGov->take(6);
        }

        $sameName = $sameNamePool->map(fn($f) => [
            'id'            => $f->id,
            'slug'          => $f->slug,
            'name'          => $f->name,
            'facility_type' => $f->facilityType ? ['name' => $f->facilityType->name] : null,
        ]);

        // ── Same Category ─────────────────────────────────────────────────────
        // Same type + same governorate first
        $sameCatPool = Facility::with(['facilityType'])
            ->where('facility_type_id', $facility->facility_type_id)
            ->where('governorate_id', $facility->governorate_id)
            ->where('id', '!=', $facility->id)
            ->limit(6)
            ->get();

        // Fill to 6 with same type from other governorates if needed
        if ($sameCatPool->count() < 6) {
            $usedIds = $sameCatPool->pluck('id')->push($facility->id);
            $extras  = Facility::with(['facilityType'])
                ->where('facility_type_id', $facility->facility_type_id)
                ->where('governorate_id', '!=', $facility->governorate_id)
                ->whereNotIn('id', $usedIds)
                ->limit(6 - $sameCatPool->count())
                ->get();
            $sameCatPool = $sameCatPool->concat($extras);
        }

        $sameCategory = $sameCatPool->map(fn($f) => [
            'id'            => $f->id,
            'slug'          => $f->slug,
            'name'          => $f->name,
            'facility_type' => $f->facilityType ? ['name' => $f->facilityType->name] : null,
        ]);

        $contracts = Contract::active()->ordered()->get()->map(function ($contract) {
            return [
                'id'          => $contract->id,
                'name'        => $contract->name,
                'description' => $contract->description,
                'phones'      => $contract->phones,
                'slug'        => $contract->slug,
                'image'       => $contract->image,
            ];
        });

        return Inertia::render('Guest/FacilityShowPage', [
            'facility'     => [
                'id'            => $facility->id,
                'slug'          => $facility->slug,
                'name'          => $facility->name,
                'facility_type' => $facility->facilityType ? ['name' => $facility->facilityType->name] : null,
                'logo'          => $facility->logo,
                'image'         => $facility->image,
                'gallery'       => $facility->gallery,
            ],
            'branches'     => $branches,
            'branchSearch' => $branchSearch,
            'sameName'     => $sameName,
            'sameCategory' => $sameCategory,
            'contracts'    => $contracts,
        ]);
    }
}
