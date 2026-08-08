<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\Show\AdminFacilityBranchShowResource;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITY_BRANCHES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES; }

    /**
     * Display the specified facility branch.
     */
    public function __invoke(Request $request, string $facilityBranch): Response
    {
        $facilityBranch = FacilityBranch::with('facility.facilityType', 'governorate', 'city')
            ->where('slug', $facilityBranch)
            ->firstOrFail();
        $this->assertOwns($facilityBranch);

        $result = [
            'facilityBranch' => (new AdminFacilityBranchShowResource($facilityBranch))->toArray($request),
        ];

        return Inertia::render('Admin/FacilityBranch/Show', $result);
    }
}

