<?php

namespace Database\Seeders\IsmailiaData;

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
            // Create Ismailia governorate
            $ismailia = Governorate::updateOrCreate(
                [
                    'slug' => 'ismailia',
                ],
                [
                    'name' => [
                        'ar' => 'الإسماعيلية',
                        'en' => 'Ismailia',
                    ],
                ]
            );

            Log::info('Ismailia governorate created/updated', [
                'id' => $ismailia->id,
                'name' => $ismailia->name,
                'slug' => $ismailia->slug,
            ]);

            $this->command->info("Governorate created: Ismailia (الإسماعيلية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


