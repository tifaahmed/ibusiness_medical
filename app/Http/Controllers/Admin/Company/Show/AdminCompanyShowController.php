<?php

namespace App\Http\Controllers\Admin\Company\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Company\Show\AdminCompanyShowResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_COMPANIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_COMPANIES;
    }

    public function __invoke(Request $request, string $company): Response
    {
        $company = Company::with('creator:id,name,email')
            ->withCount('memberships')
            ->where('slug', $company)
            ->firstOrFail();
        $this->assertOwns($company);

        return Inertia::render('Admin/Company/Show', [
            'company' => (new AdminCompanyShowResource($company))->toArray($request),
        ]);
    }
}
