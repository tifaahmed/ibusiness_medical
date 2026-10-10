<?php

use App\Enums\User\UserPermissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tags used to ride on the services permissions. They now have their own
 * (`manage tags`, `manage own tags`, `view tags`), so this creates the rows and
 * hands each one to whoever held the services permission that used to unlock
 * the same screens — nobody loses a page they could open before.
 *
 *   manage services      → manage tags
 *   manage own services  → manage own tags
 *   view services        → view tags
 *
 * Roles and direct per-user grants are both carried over. Re-runnable.
 */
return new class extends Migration
{
    private const MAP = [
        UserPermissionEnum::MANAGE_SERVICES => UserPermissionEnum::MANAGE_TAGS,
        UserPermissionEnum::MANAGE_OWN_SERVICES => UserPermissionEnum::MANAGE_OWN_TAGS,
        UserPermissionEnum::VIEW_SERVICES => UserPermissionEnum::VIEW_TAGS,
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MAP as $old => $new) {
            $newId = DB::table('permissions')->where('name', $new)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $new, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ]);

            $oldId = DB::table('permissions')->where('name', $old)->where('guard_name', 'web')->value('id');
            if (! $oldId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get(['model_type', 'model_id']) as $holder) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $newId, 'model_type' => $holder->model_type, 'model_id' => $holder->model_id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_values(self::MAP))->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
