<?php

namespace Database\Seeders\LuxorData;

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
            // Create Luxor governorate
            $luxor = Governorate::updateOrCreate(
                [
                    'slug' => 'luxor',
                ],
                [
                    'name' => [
                        'ar' => 'الأقصر',
                        'en' => 'Luxor',
                    ],
                ]
            );

            Log::info('Luxor governorate created/updated', [
                'id' => $luxor->id,
                'name' => $luxor->name,
                'slug' => $luxor->slug,
            ]);

            $this->command->info("Governorate created: Luxor (الأقصر)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


