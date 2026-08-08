<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core seeders (users, roles, etc.)
            // AdminRoleSeeder::class,
            PermissionSeeder::class,
            // AdminUserSeeder::class,
            // EditorAdminSeeder::class,
            // CitySeeder::class,
            // MemberUserSeeder::class,

            // ContractSeeder::class,
            // FaqSeeder::class,
            ServiceTypeSeeder::class,
            ServiceSeeder::class,
            PartnerOfferSeeder::class,
            TagSeeder::class
        ]);
    }
}
