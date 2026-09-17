<?php

namespace App\Http\Controllers\Admin\Product\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ProductType;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ProductSeoGenerator;
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

        $stores = Store::query()->orderBy('id')->get()->map(fn (Store $store) => [
            'id' => $store->id,
            'name' => $store->title,
        ]);

        $tags = Tag::forPicker();

        return Inertia::render('Admin/Product/Create/ProductCreateView', [
            'productTypes' => $productTypes,
            'stores' => $stores,
            'tags' => $tags,
            'seoAiEnabled' => ProductSeoGenerator::isConfigured(),
        ]);
    }
}
