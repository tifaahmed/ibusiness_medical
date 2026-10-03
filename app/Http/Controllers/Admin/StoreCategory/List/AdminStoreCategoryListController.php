<?php

namespace App\Http\Controllers\Admin\StoreCategory\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\StoreCategory\List\AdminStoreCategoryListCollection;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreCategoryListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_STORE_CATEGORIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES; }

    /**
     * Display a listing of store categories.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $storeCategories = StoreCategory::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    // Both names are listed, so both are searchable.
                    $query->where('name->en', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('name->ar', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/StoreCategory/List', [
            'storeCategories' => new AdminStoreCategoryListCollection($storeCategories)->toArray($request),
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
