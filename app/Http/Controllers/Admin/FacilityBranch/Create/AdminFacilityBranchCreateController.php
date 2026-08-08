<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Facility;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $facilities = Facility::with(['facilityType'])
            ->withCount('branches')
            ->get()
            ->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'facility_type' => $facility->facilityType ? [
                        'id' => $facility->facilityType->id,
                        'name' => $facility->facilityType->name,
                    ] : null,
                    'branches_count' => $facility->branches_count,
                ];
            });

        $governorates = Governorate::all()->map(function ($governorate) {
            return [
                'id' => $governorate->id,
                'name' => $governorate->name,
            ];
        });

        $cities = City::all()->map(function ($city) {
            return [
                'id' => $city->id,
                'governorate_id' => $city->governorate_id,
                'name' => $city->name,
            ];
        });

        return Inertia::render('Admin/FacilityBranch/Form/FacilityBranchFormView', [
            'facilities' => $facilities,
            'governorates' => $governorates,
            'cities' => $cities,
        ]);
    }
}



