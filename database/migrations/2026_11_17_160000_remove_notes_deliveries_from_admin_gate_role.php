<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The `admin` role is a GATE role: it only lets a user into the admin area and carries no
 * permissions (PermissionSeeder syncs it to []). Migrations 2026_11_03_100000 (member notes)
 * and 2026_11_03_110000 (card deliveries) wrongly granted their new permissions to it, so
 * every login-only admin got them "via role". This removes exactly those four grants from the
 * `admin` role — rows those migrations created. Permissions, other roles and per-user direct
 * grants are untouched; give them to a real role or to the user from the admin screens.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view member notes', 'add member notes', 'view card deliveries', 'manage card deliveries'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', UserRoleEnum::ADMIN)->where('guard_name', 'web')->value('id');
        if ($roleId === null) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->where('role_id', $roleId)->whereIn('permission_id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', UserRoleEnum::ADMIN)->where('guard_name', 'web')->value('id');
        if ($roleId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id') as $id) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
