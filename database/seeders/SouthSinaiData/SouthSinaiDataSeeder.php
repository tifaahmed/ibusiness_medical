<?php

namespace Database\Seeders\SouthSinaiData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SouthSinaiDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * This seeder runs all South Sinai data seeders in the correct order
     */
    public function run(): void
    {
        $this->command->info('Starting South Sinai data import...');
        
        // Run seeders in order
        $this->call([
            GovernorateSeeder::class,      // 1. Create South Sinai governorate
            FacilityTypeSeeder::class,     // 2. Create facility types from categories
            FacilitySeeder::class,         // 3. Import facilities from JSON
            FacilityBranchSeeder::class,   // 4. Create branches if any
        ]);

        $this->command->info('South Sinai data import complete!');
    }
}


