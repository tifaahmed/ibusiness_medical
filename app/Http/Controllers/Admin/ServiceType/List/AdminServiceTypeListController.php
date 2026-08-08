<?php

namespace App\Http\Controllers\Admin\ServiceType\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ServiceType\List\AdminServiceTypeListCollection;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceTypeListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');

        $serviceTypes = ServiceType::query()
            ->with(['creator:id,name,email'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name->en', 'like', "%$search%")
                          ->orWhere('name->ar', 'like', "%$search%")
                          ->orWhere('description', 'like', "%$search%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/ServiceType/List', [
            'serviceTypes' => new AdminServiceTypeListCollection($serviceTypes)->toArray($request),
            'filters'      => ['search' => $search],
        ]);
    }
}
