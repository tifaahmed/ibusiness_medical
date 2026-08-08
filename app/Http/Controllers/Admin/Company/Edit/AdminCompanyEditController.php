<?php

namespace App\Http\Controllers\Admin\Company\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Company\Edit\AdminCompanyEditResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    public function __invoke(Request $request, string $company): Response
    {
        $company = Company::where('slug', $company)->firstOrFail();
        $this->assertOwns($company);

        return Inertia::render('Admin/Company/Edit/CompanyEditView', [
            'company' => (new AdminCompanyEditResource($company))->toArray($request),
        ]);
    }
}
