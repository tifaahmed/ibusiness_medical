<?php

namespace App\Http\Controllers\Admin\Company\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Company\List\AdminCompanyListCollection;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');

        $companies = Company::query()
            ->with('creator:id,name,email')
            ->withCount('memberships')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name->' . app()->getLocale(), 'like', "%{$search}%")
                          ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Company/List', [
            'companies' => new AdminCompanyListCollection($companies)->toArray($request),
            'filters'   => ['search' => $search],
        ]);
    }
}
