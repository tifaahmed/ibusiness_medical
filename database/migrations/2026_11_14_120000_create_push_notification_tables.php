<?php

use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Push notifications to the mobile apps. Only ADDS: two new tables and one new permission (super admin only —
 * PermissionSeeder is never needed for it).
 */
return new class extends Migration
{
    private const PERMISSION = 'send push notifications';

    public function up(): void
    {
        if (! Schema::hasTable('device_push_tokens')) {
            Schema::create('device_push_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                // FCM tokens are long; the unique key is the sha256 of the token.
                $table->char('token_hash', 64)->unique();
                $table->text('token');
                $table->string('platform', 16)->nullable();
                $table->string('locale', 8)->nullable();
                $table->string('app_version', 32)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_notifications')) {
            Schema::create('push_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title', 150);
                $table->text('body');
                $table->string('url', 500)->nullable();
                // 'all' = every registered device; 'member' = only the devices of one member (a test).
                $table->string('audience', 16)->default('all');
                $table->string('audience_phone', 32)->nullable();
                $table->string('status', 16)->default('sending'); // sending | sent | failed
                $table->unsignedInteger('total')->default(0);
                $table->unsignedInteger('delivered')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->unsignedInteger('removed')->default(0);
                $table->text('error')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        $id = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->where('name', UserRoleEnum::SUPER_ADMIN)->where('guard_name', 'web')->value('id');
        if ($roleId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never drop sent-notification history or registered devices.
    }
};
