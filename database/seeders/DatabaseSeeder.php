<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // NOTE: do not add WithoutModelEvents here. Many models rely on Spatie's
    // HasSlug trait, which fills `slug` from a model event — muting events
    // makes every insert fail on the non-nullable `slug` column.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core seeders (users, roles, etc.)
            // Order matters: roles must exist before PermissionSeeder can attach
            // permissions to them, and the admin user must exist before any
            // seeder that stamps `created_by`.
            AdminRoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,

            // CitySeeder resolves its city lists by governorate slug, so
            // governorates have to exist first.
            GovernorateSeeder::class,
            CitySeeder::class,

            ContractSeeder::class,
            FaqSeeder::class,
            ServiceTypeSeeder::class,
            ServiceSeeder::class,
            PartnerOfferSeeder::class,
            TagSeeder::class
        ]);
    }
}
