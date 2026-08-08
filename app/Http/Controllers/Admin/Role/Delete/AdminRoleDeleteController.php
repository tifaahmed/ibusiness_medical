<?php

namespace App\Http\Controllers\Admin\Role\Delete;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleDeleteController extends Controller
{
    public function __invoke(Role $role): RedirectResponse
    {
        if (UserRoleEnum::isProtected($role->name)) {
            return back()->withErrors(['role' => "The {$role->name} role is protected and cannot be deleted."]);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => "Role {$role->name} is in use by one or more users and cannot be deleted."]);
        }

        $name = $role->name;
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role {$name} deleted.");
    }
}
