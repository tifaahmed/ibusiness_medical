<?php

namespace App\Http\Controllers\Admin\Role\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminRoleEditController extends Controller
{
    public function __invoke(\Illuminate\Http\Request $request, Role $role): Response
    {
        if (UserRoleEnum::isProtected($role->name)) {
            throw new HttpException(403, "The {$role->name} role is protected and cannot be edited.");
        }

        $role->load('permissions');

        return Inertia::render('Admin/Role/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'is_protected' => false,
            ],
            'all_permissions' => UserPermissionEnum::all(),
            // Monthly cap per hand-written balance permission: the default for everyone holding this role (empty = unlimited).
            'ledger_limits' => collect(\App\Support\AdminLedgerLimits::permissions())->map(fn (string $permission) => [
                'permission' => $permission,
                'granted' => $role->permissions->contains('name', $permission),
                'configured' => \App\Models\RoleLedgerLimit::query()->where('role_id', $role->id)->where('permission', $permission)->exists(),
                'monthly_limit' => \App\Models\RoleLedgerLimit::query()->where('role_id', $role->id)->where('permission', $permission)->value('monthly_limit'),
            ])->values()->all(),
            'can_edit_limits' => \App\Support\AdminLedgerLimits::isSuper($request->user()),
        ]);
    }
}
