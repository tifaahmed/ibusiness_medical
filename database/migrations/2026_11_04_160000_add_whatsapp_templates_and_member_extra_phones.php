<?php

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Setting;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Additive only:
 *  - `member_extra_phones`: other numbers kept on a member as NOTES (e.g. the one that has WhatsApp). Many per member,
 *    not unique, not users.phone, never used to sign in or to send SMS. Soft-deleted so a mistyped one can be removed
 *    without losing the record.
 *  - the two WhatsApp permissions (super admin only until a role is given them);
 *  - the WhatsApp message templates as `settings` rows, holding today's wording, only if missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_extra_phones')) {
            Schema::create('member_extra_phones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('phone', 32);
                $table->string('note', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index('user_id');
            });
        }

        $superId = DB::table('roles')->where('name', UserRoleEnum::SUPER_ADMIN)->where('guard_name', 'web')->value('id');
        foreach ([UserPermissionEnum::EDIT_WHATSAPP_MESSAGE, UserPermissionEnum::EDIT_WHATSAPP_TEMPLATES] as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            if ($superId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $superId]);
            }
        }

        foreach (QuickAdd::seedRows() as $row) {
            if (in_array($row['slug'], QuickAdd::whatsappSlugs(), true) && ! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: a table of numbers, permissions and edited texts must not be dropped by a rollback.
    }
};
