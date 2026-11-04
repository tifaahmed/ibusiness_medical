<?php

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Setting;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two permissions for the quick-add message and the editable message templates (rows in `settings`).
 * Only ADDS: permission rows (granted to the super admin role, nobody else), and template rows that do not exist yet,
 * holding today's wording. PermissionSeeder is not needed (it would drop hand-given permissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        $superId = DB::table('roles')->where('name', UserRoleEnum::SUPER_ADMIN)->where('guard_name', 'web')->value('id');

        foreach ([UserPermissionEnum::EDIT_QUICK_ADD_MESSAGE, UserPermissionEnum::EDIT_QUICK_ADD_TEMPLATES] as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

            if ($superId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $superId]);
            }
        }

        foreach (QuickAdd::seedRows() as $row) {
            if (in_array($row['slug'], QuickAdd::templateSlugs(), true) && ! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: removing a permission someone may hold, or a text an admin edited, is the lossy direction.
    }
};
