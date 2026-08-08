<?php

namespace App\Http\Controllers\Admin\Role\Update;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminRoleUpdateController extends Controller
{
    public function __invoke(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if (UserRoleEnum::isProtected($role->name)) {
            throw new HttpException(403, "The {$role->name} role is protected and cannot be updated.");
        }

        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$role->name} updated.");
    }
}
