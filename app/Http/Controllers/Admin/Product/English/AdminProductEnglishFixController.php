<?php

namespace App\Http\Controllers\Admin\Product\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Product;
use App\Services\ProductEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Backs the "Fix English fields with AI" button on the admin product form.
 * Called over axios; fills or repairs the English name, short description and
 * description of this one product from its Arabic values, then answers JSON.
 */
class AdminProductEnglishFixController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_PRODUCTS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_PRODUCTS;
    }

    public function __construct(private readonly ProductEnglishBackfiller $backfiller) {}

    public function __invoke(Request $request, string $product): JsonResponse
    {
        $model = Product::with('productType')->where('slug', $product)->firstOrFail();

        $this->assertOwns($model);

        try {
            $result = $this->backfiller->fix($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
