<?php

namespace App\Http\Controllers\Admin\Governorate\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Governorate\List\AdminGovernorateListCollection;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminGovernorateListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    /**
     * Display a listing of governorates.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $governorates = Governorate::query()
            ->with('creator:id,name,email')
            ->withCount(['facilities', 'cities'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Governorate/List', [
            'governorates' => new AdminGovernorateListCollection($governorates)->toArray($request),
            'filters' => $filters,
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
        ];
    }
}

