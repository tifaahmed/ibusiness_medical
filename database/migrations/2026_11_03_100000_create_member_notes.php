<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff notes about a member. ADD-ONLY: a new table and two new permissions.
 * `user_id` is the member, `created_by` the admin who wrote it (kept as a NAME-less id; the
 * page joins the name). Permissions are inserted here, not by re-running PermissionSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_notes')) {
            Schema::create('member_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index('created_by');
            });
        }

        $grants = [
            'view member notes' => [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR, UserRoleEnum::VIEWER],
            'add member notes' => [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR],
        ];

        foreach ($grants as $name => $roles) {
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

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
        // Left in place: dropping notes staff wrote is the lossy direction.
    }
};
