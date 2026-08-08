<?php

namespace Database\Seeders\AssiutData;

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
            // Create Assiut governorate
            $assiut = Governorate::updateOrCreate(
                [
                    'slug' => 'assiut',
                ],
                [
                    'name' => [
                        'ar' => 'أسيوط',
                        'en' => 'Assiut',
                    ],
                ]
            );

            Log::info('Assiut governorate created/updated', [
                'id' => $assiut->id,
                'name' => $assiut->name,
                'slug' => $assiut->slug,
            ]);

            $this->command->info("Governorate created: Assiut (أسيوط)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


