<?php

use App\Enums\User\UserPermissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cities and areas used to ride on the governorate permissions. They now have
 * their own (`manage|view cities`, `manage|view areas`), so this creates the
 * rows and hands them to whoever already had the governorate permission that
 * used to unlock those screens — nobody loses a page they could open before.
 *
 *   manage governorates      → manage + view cities and areas
 *   manage own governorates  → view cities and areas (they could not have run
 *                              a city they did not own; there is no "own" city)
 *   view governorates        → view cities and areas
 *
 * Roles and direct per-user grants are both carried over. Re-runnable.
 */
return new class extends Migration
{
    private const MAP = [
        UserPermissionEnum::MANAGE_GOVERNORATES => [
            UserPermissionEnum::MANAGE_CITIES, UserPermissionEnum::VIEW_CITIES,
            UserPermissionEnum::MANAGE_AREAS, UserPermissionEnum::VIEW_AREAS,
        ],
        UserPermissionEnum::MANAGE_OWN_GOVERNORATES => [UserPermissionEnum::VIEW_CITIES, UserPermissionEnum::VIEW_AREAS],
        UserPermissionEnum::VIEW_GOVERNORATES => [UserPermissionEnum::VIEW_CITIES, UserPermissionEnum::VIEW_AREAS],
    ];

    public function up(): void
    {
        $now = now();
        $ids = [];

        foreach ([UserPermissionEnum::MANAGE_CITIES, UserPermissionEnum::VIEW_CITIES, UserPermissionEnum::MANAGE_AREAS, UserPermissionEnum::VIEW_AREAS] as $name) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $ids[$name] = $existing ?? DB::table('permissions')->insertGetId([
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::MAP as $old => $new) {
            $oldId = DB::table('permissions')->where('name', $old)->where('guard_name', 'web')->value('id');
            if (! $oldId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $roleId) {
                foreach ($new as $name) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $ids[$name], 'role_id' => $roleId]);
                }
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get(['model_type', 'model_id']) as $holder) {
                foreach ($new as $name) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $ids[$name], 'model_type' => $holder->model_type, 'model_id' => $holder->model_id,
                    ]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', [
            UserPermissionEnum::MANAGE_CITIES, UserPermissionEnum::VIEW_CITIES,
            UserPermissionEnum::MANAGE_AREAS, UserPermissionEnum::VIEW_AREAS,
        ])->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
