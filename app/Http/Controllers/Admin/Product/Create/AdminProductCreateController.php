<?php

namespace App\Http\Controllers\Admin\Product\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ProductType;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductCreateController extends BaseController
{
    public function __invoke(): Response
    {
        $productTypes = ProductType::all()->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
        ]);

        $tags = Tag::forPicker();

        return Inertia::render('Admin/Product/Create/ProductCreateView', [
            'productTypes' => $productTypes,
            'tags' => $tags,
        ]);
    }
}
