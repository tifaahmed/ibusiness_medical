<?php

namespace App\Http\Controllers\Admin\Sales\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Sales\Edit\AdminSalesEditResource;
use App\Models\Sales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SALES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SALES; }

    public function __invoke(Request $request, Sales $sale): Response
    {
        $this->assertOwns($sale);

        return Inertia::render('Admin/Sales/Form/SalesFormView', [
            'sale' => (new AdminSalesEditResource($sale))->toArray($request),
        ]);
    }
}
