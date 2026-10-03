<?php

namespace App\Http\Controllers\Admin\StoreCategory\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\StoreCategory\Edit\AdminStoreCategoryEditResource;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreCategoryEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_STORE_CATEGORIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES; }

    /**
     * Show the form for editing the specified store category.
     */
    public function __invoke(Request $request, string $storeCategory): Response
    {
        $storeCategory = StoreCategory::where('slug', $storeCategory)->firstOrFail();
        $this->assertOwns($storeCategory);

        $result = [
            'storeCategory' => (new AdminStoreCategoryEditResource($storeCategory))->toArray($request),
        ];

        return Inertia::render('Admin/StoreCategory/Edit/StoreCategoryEditView', $result);
    }
}
