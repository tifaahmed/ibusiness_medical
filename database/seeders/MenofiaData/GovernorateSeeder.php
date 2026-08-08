<?php

namespace Database\Seeders\MenofiaData;

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
            // Create Menofia governorate
            $menofia = Governorate::updateOrCreate(
                [
                    'slug' => 'menofia',
                ],
                [
                    'name' => [
                        'ar' => 'المنوفية',
                        'en' => 'Menofia',
                    ],
                ]
            );

            Log::info('Menofia governorate created/updated', [
                'id' => $menofia->id,
                'name' => $menofia->name,
                'slug' => $menofia->slug,
            ]);

            $this->command->info("Governorate created: Menofia (المنوفية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


