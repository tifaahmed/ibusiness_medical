<?php

namespace Database\Seeders\KafrElSheikhData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KafrElSheikhDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * This seeder runs all Kafr El Sheikh data seeders in the correct order
     */
    public function run(): void
    {
        $this->command->info('Starting Kafr El Sheikh data import...');
        
        // Run seeders in order
        $this->call([
            GovernorateSeeder::class,      // 1. Create Kafr El Sheikh governorate
            FacilityTypeSeeder::class,     // 2. Create facility types from categories
            FacilitySeeder::class,         // 3. Import facilities from JSON
            FacilityBranchSeeder::class,   // 4. Create branches if any
        ]);

        $this->command->info('Kafr El Sheikh data import complete!');
    }
}


