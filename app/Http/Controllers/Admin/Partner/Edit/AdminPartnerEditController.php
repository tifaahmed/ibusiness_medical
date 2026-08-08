<?php

namespace App\Http\Controllers\Admin\Partner\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Partner\Edit\AdminPartnerEditResource;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNERS; }

    public function __invoke(Request $request, Partner $partner): Response
    {
        $this->assertOwns($partner);

        return Inertia::render('Admin/Partner/Form/PartnerFormView', [
            'partner' => (new AdminPartnerEditResource($partner))->toArray($request),
        ]);
    }
}
