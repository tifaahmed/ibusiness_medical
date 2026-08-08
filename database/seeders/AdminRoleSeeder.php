<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Enums\User\UserRoleEnum;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            foreach(UserRoleEnum::getRoles() as $role) {
                $created = Role::firstOrCreate(
                    ['name' => $role['name'], 'guard_name' => 'web']
                );
                Log::info('Role ensured', [
                    'id' => $created->id,
                    'name' => $created->name,
                    'label' => $role['label'],
                ]);
                $this->command->info("Role id: " . $created->id);
                $this->command->info("Role ensured: " . $created->name);
                $this->command->info("Role label: " . $role['label']);
            }
        } catch (\Exception $e) {
            Log::error('Error creating roles: ' . $e->getMessage());
            $this->command->error('Error creating roles: ' . $e->getMessage());
        }
    }
}
