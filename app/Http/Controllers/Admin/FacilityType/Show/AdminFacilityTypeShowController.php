<?php

namespace App\Http\Controllers\Admin\FacilityType\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityType\Show\AdminFacilityTypeShowResource;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityTypeShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Display the specified facility type.
     */
    public function __invoke(Request $request, string $facilityType): Response
    {
        $facilityType = FacilityType::withCount('facilities')
            ->with('facilities.governorate')
            ->where('slug', $facilityType)
            ->firstOrFail();
        $this->assertOwns($facilityType);

        $result = [
            'facilityType' => (new AdminFacilityTypeShowResource($facilityType))->toArray($request),
        ];

        return Inertia::render('Admin/FacilityType/Show', $result);
    }
}

