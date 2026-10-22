<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds `view visitors` and grants it to the super admin and the viewer role —
 * and ONLY adds. `PermissionSeeder` re-syncs the editor, admin and viewer roles
 * and would drop any permission an admin handed them by hand, so a deploy must
 * not need it just to make a new permission exist.
 */
return new class extends Migration
{
    private const NAME = 'view visitors';

    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::NAME)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => self::NAME,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ([UserRoleEnum::SUPER_ADMIN, UserRoleEnum::VIEWER] as $role) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');

            if ($roleId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: removing a permission someone may have granted is the lossy direction.
    }
};
