<?php

namespace App\Http\Controllers\Admin\Service\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Service\Show\AdminServiceShowResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request, string $service): Response
    {
        $service = Service::where('slug', $service)->firstOrFail();
        $this->assertOwns($service);

        return Inertia::render('Admin/Service/Show', [
            'service' => new AdminServiceShowResource($service),
        ]);
    }
}
