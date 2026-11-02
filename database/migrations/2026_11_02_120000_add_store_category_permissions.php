<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `manage store categories`, `manage own store categories` and `view store categories` were added to UserPermissionEnum
 * (and are offered on the admin-user and role forms) but never inserted, so saving an admin who ticked one died with
 * PermissionDoesNotExist (500). Adds the rows and grants them to the super admin (`view` also to the viewer role) — and
 * ONLY adds: `PermissionSeeder` would re-sync the editor/admin/viewer roles and drop permissions handed out by hand.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['manage store categories', 'manage own store categories', 'view store categories'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

            $roles = [UserRoleEnum::SUPER_ADMIN];
            if (str_starts_with($name, 'view ')) {
                $roles[] = UserRoleEnum::VIEWER;
            }

            foreach ($roles as $role) {
                $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
                if ($roleId !== null) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: removing a permission someone may have been granted is the lossy direction.
    }
};
