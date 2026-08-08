<?php

namespace App\Http\Controllers\Admin\AdminUser\Create;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminUserCreateController extends Controller
{
    public function __invoke(): Response
    {
        $assignableRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::MEMBER])
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->values()->all(),
                'description' => UserRoleEnum::descriptionFor($r->name),
            ])
            ->values();

        $partners = Partner::orderBy('title')->get(['id', 'title'])->map(fn (Partner $p) => [
            'value' => $p->id,
            'label' => $p->title,
        ])->values();

        return Inertia::render('Admin/AdminUser/Create', [
            'assignable_roles' => $assignableRoles,
            'all_permissions' => UserPermissionEnum::all(),
            'partner_options' => $partners,
        ]);
    }
}
