<?php

namespace App\Http\Controllers\Admin\StoreCategory\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\StoreCategory\Show\AdminStoreCategoryShowResource;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreCategoryShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_STORE_CATEGORIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES; }

    /**
     * Display the specified store category.
     */
    public function __invoke(Request $request, string $storeCategory): Response
    {
        $storeCategory = StoreCategory::query()
            ->with('creator:id,name,email')
            ->withCount('stores')
            ->where('slug', $storeCategory)
            ->firstOrFail();
        $this->assertOwns($storeCategory);

        $result = [
            'storeCategory' => (new AdminStoreCategoryShowResource($storeCategory))->resolve($request),
        ];

        return Inertia::render('Admin/StoreCategory/Show', $result);
    }
}
