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
            $admin = User::updateOrCreate([
                'email' => 'admin@secure-membership-portal.com',
            ], [
                'name' => 'Site Admin',
                'password' => Hash::make('Adm1n$ecur3P@ssw0rd2024!'),
                'email_verified_at' => now(),
                'slug' => 'site-admin',
            ]);
            Log::info('Admin user created successfully', [
                'email' => 'admin@secure-membership-portal.com',
                'name' => 'Site Admin',
                'password' => 'Adm1n$ecur3P@ssw0rd2024!'
            ]);
            $this->command->info("admin user: admin@secure-membership-portal.com");
            $this->command->info('password: Adm1n$ecur3P@ssw0rd2024!');

            $admin->syncRoles([UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN]);

        } catch (\Exception $e) {
            Log::error('Error creating admin user: ' . $e->getMessage());
            $this->command->error('Error creating admin user: ' . $e->getMessage());
        }
    }
}
