<?php

namespace App\Http\Controllers\Admin\Product\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Product\Edit\AdminProductEditResource;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PRODUCTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PRODUCTS; }

    /**
     * Show the form for editing the specified product.
     */
    public function __invoke(Request $request, string $product): Response
    {
        $product = Product::with(['tags', 'media'])->where('slug', $product)->firstOrFail();
        $this->assertOwns($product);

        $productTypes = ProductType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });

        $tags = Tag::orderBy('name')->get(['id', 'name', 'icon', 'color']);

        $result = [
            'product' => (new AdminProductEditResource($product))->toArray($request),
            'productTypes' => $productTypes,
            'tags' => $tags,
        ];

        return Inertia::render('Admin/Product/Edit/ProductEditView', $result);
    }
}
