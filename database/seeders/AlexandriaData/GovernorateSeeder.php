<?php

namespace Database\Seeders\AlexandriaData;

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
            // Create Alexandria governorate
            $alexandria = Governorate::updateOrCreate(
                [
                    'slug' => 'alexandria',
                ],
                [
                    'name' => [
                        'ar' => 'الإسكندرية',
                        'en' => 'Alexandria',
                    ],
                ]
            );

            Log::info('Alexandria governorate created/updated', [
                'id' => $alexandria->id,
                'name' => $alexandria->name,
                'slug' => $alexandria->slug,
            ]);

            $this->command->info("Governorate created: Alexandria (الإسكندرية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}



