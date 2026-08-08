<?php

namespace Database\Seeders\SuezData;

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
            // Create Suez governorate
            $suez = Governorate::updateOrCreate(
                [
                    'slug' => 'suez',
                ],
                [
                    'name' => [
                        'ar' => 'السويس',
                        'en' => 'Suez',
                    ],
                ]
            );

            Log::info('Suez governorate created/updated', [
                'id' => $suez->id,
                'name' => $suez->name,
                'slug' => $suez->slug,
            ]);

            $this->command->info("Governorate created: Suez (السويس)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


