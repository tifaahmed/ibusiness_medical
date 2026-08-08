<?php

namespace Database\Seeders\NewValleyData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NewValleyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * This seeder runs all New Valley data seeders in the correct order
     */
    public function run(): void
    {
        $this->command->info('Starting New Valley data import...');
        
        // Run seeders in order
        $this->call([
            GovernorateSeeder::class,      // 1. Create New Valley governorate
            FacilityTypeSeeder::class,     // 2. Create facility types from categories
            FacilitySeeder::class,         // 3. Import facilities from JSON
            FacilityBranchSeeder::class,   // 4. Create branches if any
        ]);

        $this->command->info('New Valley data import complete!');
    }
}


