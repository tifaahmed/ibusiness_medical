<?php

namespace App\Http\Controllers\Admin\Store\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    public function __invoke(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $stores = Store::query()
            ->withCount(['branches', 'products'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('title->'.app()->getLocale(), 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Store $store) => [
                'id' => $store->id,
                'title' => $store->title,
                'logo' => $store->logo,
                'branches_count' => $store->branches_count,
                'products_count' => $store->products_count,
                'created_at' => $store->created_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Admin/Store/List/StoreListView', [
            'stores' => $stores,
            'filters' => ['search' => $search],
            'canManage' => $request->user()?->hasAnyPermission([
                UserPermissionEnum::MANAGE_STORES,
                UserPermissionEnum::MANAGE_OWN_STORES,
            ]) ?? false,
        ]);
    }
}
