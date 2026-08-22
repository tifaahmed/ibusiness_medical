<?php

namespace App\Http\Controllers\Admin\Sales\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Sales\List\AdminSalesListCollection;
use App\Models\Sales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_SALES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_SALES;
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $sales = Sales::query()
            ->with([
                'facilities:id,sales_id,name,slug,facility_type_id',
                'facilities.facilityType:id,name',
            ])
            ->withCount('facilities')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Sales/List', [
            'sales' => (new AdminSalesListCollection($sales))->toArray($request),
            'filters' => $filters,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
        ];
    }
}
