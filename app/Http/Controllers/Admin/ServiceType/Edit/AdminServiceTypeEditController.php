<?php

namespace App\Http\Controllers\Admin\ServiceType\Edit;

use App\Enums\ServiceType\ServiceTypeEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ServiceType\Show\AdminServiceTypeShowResource;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceTypeEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request, int $serviceType): Response
    {
        $serviceType = ServiceType::findOrFail($serviceType);
        $this->assertOwns($serviceType);

        return Inertia::render('Admin/ServiceType/Edit/ServiceTypeEditView', [
            'serviceType' => new AdminServiceTypeShowResource($serviceType),
            'iconOptions' => ServiceTypeEnum::getIconOptions(),
            'colorOptions' => ServiceTypeEnum::getColorOptions(),
        ]);
    }
}
