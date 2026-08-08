<?php

namespace App\Http\Controllers\Admin\Partner\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Partner\List\AdminPartnerListCollection;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNERS; }

    public function __invoke(Request $request): Response
    {
        $partners = Partner::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Partner/List', [
            'partners' => (new AdminPartnerListCollection($partners))->toArray($request),
        ]);
    }
}
