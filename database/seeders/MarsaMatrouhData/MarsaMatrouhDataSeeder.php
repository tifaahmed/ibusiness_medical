<?php

namespace Database\Seeders\MarsaMatrouhData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MarsaMatrouhDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * This seeder runs all Marsa Matrouh data seeders in the correct order
     */
    public function run(): void
    {
        $this->command->info('Starting Marsa Matrouh data import...');
        
        // Run seeders in order
        $this->call([
            GovernorateSeeder::class,      // 1. Create Marsa Matrouh governorate
            FacilityTypeSeeder::class,     // 2. Create facility types from categories
            FacilitySeeder::class,         // 3. Import facilities from JSON
            FacilityBranchSeeder::class,   // 4. Create branches if any
        ]);

        $this->command->info('Marsa Matrouh data import complete!');
    }
}


