<?php

namespace Database\Seeders\DakahliaData;

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
            // Create Dakahlia governorate
            $dakahlia = Governorate::updateOrCreate(
                [
                    'slug' => 'dakahlia',
                ],
                [
                    'name' => [
                        'ar' => 'الدقهلية',
                        'en' => 'Dakahlia',
                    ],
                ]
            );

            Log::info('Dakahlia governorate created/updated', [
                'id' => $dakahlia->id,
                'name' => $dakahlia->name,
                'slug' => $dakahlia->slug,
            ]);

            $this->command->info("Governorate created: Dakahlia (الدقهلية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


