<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Four permissions for writing to a member's balances by hand (add / take × loyalty points / wallet money) and the table that
 * holds an optional MONTHLY cap per admin and permission (`monthly_limit` NULL = unlimited, and so is a missing row). Nobody but the
 * super admin is granted the new permissions: giving them out, and capping them, is the owner's call. Add-only.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['add loyalty points', 'take loyalty points', 'add wallet money', 'take wallet money'];

    public function up(): void
    {
        if (! Schema::hasTable('admin_ledger_limits')) {
            Schema::create('admin_ledger_limits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('permission', 64);
                $table->decimal('monthly_limit', 12, 2)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'permission']);
            });
        }

        $superId = DB::table('roles')->where('name', UserRoleEnum::SUPER_ADMIN)->where('guard_name', 'web')->value('id');

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

            if ($superId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $superId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: dropping the table would lose the caps, and a permission someone was granted should not vanish.
    }
};
