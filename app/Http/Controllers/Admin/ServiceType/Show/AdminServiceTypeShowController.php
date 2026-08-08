<?php

namespace App\Http\Controllers\Admin\ServiceType\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ServiceType\Show\AdminServiceTypeShowResource;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceTypeShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request, int $serviceType): Response
    {
        $serviceType = ServiceType::with(['creator:id,name,email'])->findOrFail($serviceType);
        $this->assertOwns($serviceType);

        return Inertia::render('Admin/ServiceType/Show', [
            'serviceType' => new AdminServiceTypeShowResource($serviceType),
        ]);
    }
}
