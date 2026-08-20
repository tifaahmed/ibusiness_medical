<?php

namespace App\Http\Controllers\Admin\ProductType\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ProductType\Edit\AdminProductTypeEditResource;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductTypeEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCT_TYPES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCT_TYPES; }

    /**
     * Show the form for editing the specified product type.
     */
    public function __invoke(Request $request, string $productType): Response
    {
        $productType = ProductType::where('slug', $productType)->firstOrFail();
        $this->assertOwns($productType);

        $result = [
            'productType' => (new AdminProductTypeEditResource($productType))->toArray($request),
        ];

        return Inertia::render('Admin/ProductType/Edit/ProductTypeEditView', $result);
    }
}
