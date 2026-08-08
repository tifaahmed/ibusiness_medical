<?php

namespace Database\Seeders\NorthSinaiData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Governorate;
use Illuminate\Support\Facades\Log;

class GovernorateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Create North Sinai governorate
            $northSinai = Governorate::updateOrCreate(
                [
                    'slug' => 'north-sinai',
                ],
                [
                    'name' => [
                        'ar' => 'شمال سيناء',
                        'en' => 'North Sinai',
                    ],
                ]
            );

            Log::info('North Sinai governorate created/updated', [
                'id' => $northSinai->id,
                'name' => $northSinai->name,
                'slug' => $northSinai->slug,
            ]);

            $this->command->info("Governorate created: North Sinai (شمال سيناء)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


