<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Delivery couriers and the card hand-overs they make. ADD-ONLY: three new tables and two new permissions
 * (inserted here — PermissionSeeder must not be re-run for them).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_couriers')) {
            Schema::create('delivery_couriers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('card_deliveries')) {
            Schema::create('card_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('delivery_couriers')->nullOnDelete();
                $table->string('status', 20)->default('delivered'); // scheduled | delivered | failed
                $table->unsignedSmallInteger('cards_count')->default(1);
                $table->string('card_numbers')->nullable();
                $table->date('delivered_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['courier_id', 'status']);
            });
        }

        if (! Schema::hasTable('card_delivery_notes')) {
            Schema::create('card_delivery_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('card_delivery_id')->constrained('card_deliveries')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }

        $grants = [
            'view card deliveries' => [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR, UserRoleEnum::VIEWER],
            'manage card deliveries' => [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR],
        ];
        foreach ($grants as $name => $roles) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($roles as $role) {
                $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
                if ($roleId !== null) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: dropping delivery history is the lossy direction.
    }
};
