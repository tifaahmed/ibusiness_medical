<?php

namespace App\Http\Controllers\Admin\Product\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Product\List\AdminProductListCollection;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCTS; }

    /**
     * Display a listing of products.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $products = Product::query()
            ->with(['creator:id,name,email', 'productType:id,name', 'tags:id,name,icon,color'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Product/List', [
            'products' => new AdminProductListCollection($products)->toArray($request),
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
