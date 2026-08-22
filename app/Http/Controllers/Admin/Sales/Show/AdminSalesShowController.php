<?php

namespace App\Http\Controllers\Admin\Sales\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Sales\Show\AdminSalesShowResource;
use App\Models\Sales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesShowController extends BaseController
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

    public function __invoke(Request $request, Sales $sale): Response
    {
        $sale->load([
            'creator:id,name,email',
            'facilities:id,sales_id,name,slug,facility_type_id',
            'facilities.facilityType:id,name',
        ])->loadCount('facilities');
        $this->assertOwns($sale);

        return Inertia::render('Admin/Sales/Show', [
            'sale' => (new AdminSalesShowResource($sale))->toArray($request),
        ]);
    }
}
