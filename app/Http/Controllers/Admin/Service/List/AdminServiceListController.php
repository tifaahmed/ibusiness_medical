<?php

namespace App\Http\Controllers\Admin\Service\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Service\List\AdminServiceListCollection;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');

        $services = Service::query()
            ->with(['category', 'governorate', 'city', 'creator:id,name,email', 'tags:id,name,icon,color'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%$search%")
                          ->orWhere('short_subject', 'like', "%$search%")
                          ->orWhere('subject', 'like', "%$search%")
                          ->orWhere('slug', 'like', "%$search%")
                          ->orWhere('new_price', 'like', "%$search%")
                          ->orWhere('old_price', 'like', "%$search%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Service/List', [
            'services' => new AdminServiceListCollection($services)->toArray($request),
            'filters'   => ['search' => $search],
        ]);
    }
}
