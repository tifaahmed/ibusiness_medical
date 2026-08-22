<?php

namespace App\Http\Controllers\Admin\ProductType\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\ProductType\Show\AdminProductTypeShowResource;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductTypeShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCT_TYPES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCT_TYPES; }

    /**
     * Display the specified product type.
     */
    public function __invoke(Request $request, string $productType): Response
    {
        $productType = ProductType::query()
            ->with('creator:id,name,email')
            ->withCount('products')
            ->where('slug', $productType)
            ->firstOrFail();
        $this->assertOwns($productType);

        $result = [
            'productType' => (new AdminProductTypeShowResource($productType))->resolve($request),
        ];

        return Inertia::render('Admin/ProductType/Show', $result);
    }
}
