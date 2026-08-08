<?php

namespace Database\Seeders\NewValleyData;

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
            // Create New Valley governorate
            $newValley = Governorate::updateOrCreate(
                [
                    'slug' => 'new-valley',
                ],
                [
                    'name' => [
                        'ar' => 'الوادى الجديد',
                        'en' => 'New Valley',
                    ],
                ]
            );

            Log::info('New Valley governorate created/updated', [
                'id' => $newValley->id,
                'name' => $newValley->name,
                'slug' => $newValley->slug,
            ]);

            $this->command->info("Governorate created: New Valley (الوادى الجديد)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


