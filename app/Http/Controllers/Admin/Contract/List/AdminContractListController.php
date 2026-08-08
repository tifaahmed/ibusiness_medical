<?php

namespace App\Http\Controllers\Admin\Contract\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Contract\List\AdminContractListCollection;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_CONTRACTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_CONTRACTS; }

    /**
     * Display a listing of contracts.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $contracts = Contract::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', function ($q) use ($filters) {
                $q->where('is_active', (bool) $filters['is_active']);
            })
            ->orderBy('sort_order', 'asc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Contract/List', [
            'contracts' => new AdminContractListCollection($contracts)->toArray($request),
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
            'is_active' => $request->input('is_active', ''),
        ];
    }
}
