<?php

namespace App\Http\Controllers\Admin\AdminUser\Index;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUser\AdminUserCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $roleFilter = $request->string('role')->toString() ?: null;

        $admins = User::with(['roles', 'permissions'])
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', UserRoleEnum::MEMBER))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->search($search);
            })
            ->when($roleFilter, function ($query, string $role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/AdminUser/List', [
            'admins' => (new AdminUserCollection($admins))->toArray($request),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'role' => $roleFilter,
            ],
        ]);
    }
}
