<?php

namespace Database\Seeders;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EditorAdminSeeder extends Seeder
{
    public function run(): void
    {
        try {
            User::where('email', 'editor@secure-membership-portal.com')->forceDelete();

            $editors = [
                ['name' => 'Editor One',   'email' => 'editor1@secure-membership-portal.com', 'slug' => 'editor-one'],
                ['name' => 'Editor Two',   'email' => 'editor2@secure-membership-portal.com', 'slug' => 'editor-two'],
                ['name' => 'Editor Three', 'email' => 'editor3@secure-membership-portal.com', 'slug' => 'editor-three'],
            ];

            $password = 'Editor$ecur3P@ssw0rd2024!';

            foreach ($editors as $data) {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make($password),
                        'email_verified_at' => now(),
                        'slug' => $data['slug'],
                    ]
                );

                $user->syncRoles([UserRoleEnum::EDITOR]);

                Log::info('Editor admin user created', [
                    'email' => $data['email'],
                    'roles' => [UserRoleEnum::EDITOR],
                ]);
                $this->command->info("editor admin: {$data['email']}");
            }

            $this->command->info("password (all editors): {$password}");
        } catch (\Exception $e) {
            Log::error('Error creating editor admins: ' . $e->getMessage());
            $this->command->error('Error creating editor admins: ' . $e->getMessage());
        }
    }
}
