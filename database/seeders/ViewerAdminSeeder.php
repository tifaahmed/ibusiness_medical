<?php

namespace Database\Seeders;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The read-only admin: the `viewer` role, its permissions, and one account
 * holding it.
 *
 * Self-contained on purpose. It creates the permissions and the role itself
 * rather than leaning on PermissionSeeder, so it can be run on its own against
 * an existing database — PermissionSeeder re-syncs super_admin/admin/editor,
 * which would discard permissions tuned by hand since the last deploy.
 */
class ViewerAdminSeeder extends Seeder
{
    public function run(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (UserPermissionEnum::readOnlyAccess() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $role = Role::firstOrCreate(['name' => UserRoleEnum::VIEWER, 'guard_name' => 'web']);
            $role->syncPermissions(UserPermissionEnum::readOnlyAccess());

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $email = 'viewer@secure-membership-portal.com';
            $password = 'Viewer$ecur3P@ssw0rd2024!';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Read-only Viewer',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'slug' => 'read-only-viewer',
                ]
            );

            $user->syncRoles([UserRoleEnum::VIEWER]);

            Log::info('Viewer admin user seeded', ['email' => $email]);
            $this->command->info('viewer role permissions: '.count(UserPermissionEnum::readOnlyAccess()));
            $this->command->info("viewer admin: {$email}");
            $this->command->info("password: {$password}");
        } catch (\Exception $e) {
            Log::error('Error seeding viewer admin: '.$e->getMessage());
            $this->command->error('Error seeding viewer admin: '.$e->getMessage());
        }
    }
}
