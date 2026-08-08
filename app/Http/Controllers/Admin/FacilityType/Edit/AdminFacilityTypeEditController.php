<?php

namespace App\Http\Controllers\Admin\FacilityType\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityType\Edit\AdminFacilityTypeEditResource;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityTypeEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Show the form for editing the specified facility type.
     */
    public function __invoke(Request $request, string $facilityType): Response
    {
        $facilityType = FacilityType::where('slug', $facilityType)->firstOrFail();
        $this->assertOwns($facilityType);

        $result = [
            'facilityType' => (new AdminFacilityTypeEditResource($facilityType))->toArray($request),
        ];

        return Inertia::render('Admin/FacilityType/Edit/FacilityTypeEditView', $result);
    }
}

