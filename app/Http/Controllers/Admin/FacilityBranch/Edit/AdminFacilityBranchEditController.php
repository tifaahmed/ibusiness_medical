<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\Edit\AdminFacilityBranchEditResource;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITY_BRANCHES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES; }

    /**
     * Show the form for editing the specified facility branch.
     */
    public function __invoke(Request $request, string $facilityBranch): Response
    {
        $facilityBranch = FacilityBranch::where('slug', $facilityBranch)->firstOrFail();
        $this->assertOwns($facilityBranch);

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

        $result = [
            'facilityBranch' => (new AdminFacilityBranchEditResource($facilityBranch))->toArray($request),
            'facilities' => $facilities,
            'governorates' => $governorates,
            'cities' => $cities,
        ];

        return Inertia::render('Admin/FacilityBranch/Form/FacilityBranchFormView', $result);
    }
}

