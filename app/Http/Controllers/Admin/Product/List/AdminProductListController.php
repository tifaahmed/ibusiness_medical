<?php

namespace App\Http\Controllers\Admin\Product\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Product\List\AdminProductListCollection;
use App\Models\Product;
use App\Models\User;
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
                    // Both names are listed, so both are searchable.
                    $query->where('name->en', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('name->ar', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(!empty($filters['creator_id']), fn($q) => $q->where('created_by', $filters['creator_id']))
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        // Creator dropdown: distinct creators from the admin's visible products.
        // A creator-scoped admin only ever sees their own id here, and the
        // dropdown itself is hidden for them via `canFilterByCreator`.
        $creatorQuery = Product::query()
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->en', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('name->ar', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->whereNotNull('created_by')
            ->select('created_by')
            ->distinct();
        $creatorOptions = User::whereIn('id', $creatorQuery)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'email' => $u->email])
            ->toArray();

        return Inertia::render('Admin/Product/List', [
            'products' => new AdminProductListCollection($products)->toArray($request),
            'filters' => $filters,
            'creatorOptions' => $creatorOptions,
            'canFilterByCreator' => !$this->scopesToCreator(),
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'creator_id' => $request->filled('creator_id') ? (int) $request->input('creator_id') : null,
        ];
    }
}
