<?php

namespace Database\Seeders;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (UserPermissionEnum::all() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $superAdmin = Role::findByName(UserRoleEnum::SUPER_ADMIN, 'web');
            $superAdmin->syncPermissions(UserPermissionEnum::all());

            // The `admin` role is a gate role: it only grants entry to the admin area.
            // Real abilities come from other stacked roles (editor, super_admin, custom)
            // or per-user direct permissions assigned in the admin-users edit screen.
            $admin = Role::findByName(UserRoleEnum::ADMIN, 'web');
            $admin->syncPermissions([]);

            $editor = Role::findByName(UserRoleEnum::EDITOR, 'web');
            $editor->syncPermissions(UserPermissionEnum::editorPermissions());

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->command->info('Permissions seeded and assigned to roles.');
        } catch (\Exception $e) {
            Log::error('Error seeding permissions: ' . $e->getMessage());
            $this->command->error('Error seeding permissions: ' . $e->getMessage());
        }
    }
}
