<?php

namespace Database\Seeders\NorthSinaiData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NorthSinaiDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * This seeder runs all North Sinai data seeders in the correct order
     */
    public function run(): void
    {
        $this->command->info('Starting North Sinai data import...');
        
        // Run seeders in order
        $this->call([
            GovernorateSeeder::class,      // 1. Create North Sinai governorate
            FacilityTypeSeeder::class,     // 2. Create facility types from categories
            FacilitySeeder::class,         // 3. Import facilities from JSON
            FacilityBranchSeeder::class,   // 4. Create branches if any
        ]);

        $this->command->info('North Sinai data import complete!');
    }
}


