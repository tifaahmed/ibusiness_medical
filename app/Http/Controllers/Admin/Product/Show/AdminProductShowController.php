<?php

namespace App\Http\Controllers\Admin\Product\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Product\Show\AdminProductShowResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCTS; }

    /**
     * Display the specified product.
     */
    public function __invoke(Request $request, string $product): Response
    {
        $product = Product::with(['tags', 'productType', 'creator:id,name,email'])
            ->where('slug', $product)
            ->firstOrFail();
        $this->assertOwns($product);

        $result = [
            'product' => (new AdminProductShowResource($product))->toArray($request),
        ];

        return Inertia::render('Admin/Product/Show', $result);
    }
}
