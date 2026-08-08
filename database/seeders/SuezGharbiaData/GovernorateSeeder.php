<?php

namespace Database\Seeders\SuezGharbiaData;

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
            // Create Suez/Gharbia governorate
            $suezGharbia = Governorate::updateOrCreate(
                [
                    'slug' => 'suez-gharbia',
                ],
                [
                    'name' => [
                        'ar' => 'السويس / الغربية (طنطا والمحلة)',
                        'en' => 'Suez / Gharbia (Tanta & Mahalla)',
                    ],
                ]
            );

            Log::info('Suez/Gharbia governorate created/updated', [
                'id' => $suezGharbia->id,
                'name' => $suezGharbia->name,
                'slug' => $suezGharbia->slug,
            ]);

            $this->command->info("Governorate created: Suez / Gharbia (السويس / الغربية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


