<?php

namespace Database\Seeders\SouthSinaiData;

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
            // Create South Sinai governorate
            $southSinai = Governorate::updateOrCreate(
                [
                    'slug' => 'south-sinai',
                ],
                [
                    'name' => [
                        'ar' => 'جنوب سيناء',
                        'en' => 'South Sinai',
                    ],
                ]
            );

            Log::info('South Sinai governorate created/updated', [
                'id' => $southSinai->id,
                'name' => $southSinai->name,
                'slug' => $southSinai->slug,
            ]);

            $this->command->info("Governorate created: South Sinai (جنوب سيناء)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


