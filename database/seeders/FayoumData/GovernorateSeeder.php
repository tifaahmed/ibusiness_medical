<?php

namespace Database\Seeders\FayoumData;

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
            // Create Fayoum governorate
            $fayoum = Governorate::updateOrCreate(
                [
                    'slug' => 'fayoum',
                ],
                [
                    'name' => [
                        'ar' => 'الفيوم',
                        'en' => 'Fayoum',
                    ],
                ]
            );

            Log::info('Fayoum governorate created/updated', [
                'id' => $fayoum->id,
                'name' => $fayoum->name,
                'slug' => $fayoum->slug,
            ]);

            $this->command->info("Governorate created: Fayoum (الفيوم)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


