<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Enums\User\UserRoleEnum;
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Single source of truth for the credential, so the address that
            // gets created is the same one reported back. The log and console
            // lines used to name a different domain than the row they made,
            // which sent anyone reading them to a login that cannot succeed.
            $email = 'admin@deilar.com';
            $password = 'Adm1n$ecur3P@ssw0rd2024!';

            $admin = User::updateOrCreate([
                'email' => $email,
            ], [
                'name' => 'Site Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'slug' => 'site-admin',
            ]);
            Log::info('Admin user created successfully', [
                'email' => $email,
                'name' => 'Site Admin',
            ]);
            $this->command->info("admin user: {$email}");
            $this->command->info("password: {$password}");

            $admin->syncRoles([UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN]);

        } catch (\Exception $e) {
            Log::error('Error creating admin user: ' . $e->getMessage());
            $this->command->error('Error creating admin user: ' . $e->getMessage());
        }
    }
}
