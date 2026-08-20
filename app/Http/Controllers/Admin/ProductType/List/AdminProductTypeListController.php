<?php

namespace App\Http\Controllers\Admin\ProductType\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ProductType\List\AdminProductTypeListCollection;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductTypeListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCT_TYPES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCT_TYPES; }

    /**
     * Display a listing of product types.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $productTypes = ProductType::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/ProductType/List', [
            'productTypes' => new AdminProductTypeListCollection($productTypes)->toArray($request),
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
