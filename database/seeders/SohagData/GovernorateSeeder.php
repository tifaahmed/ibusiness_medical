<?php

namespace Database\Seeders\SohagData;

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
            // Create Sohag governorate
            $sohag = Governorate::updateOrCreate(
                [
                    'slug' => 'sohag',
                ],
                [
                    'name' => [
                        'ar' => 'سوهاج',
                        'en' => 'Sohag',
                    ],
                ]
            );

            Log::info('Sohag governorate created/updated', [
                'id' => $sohag->id,
                'name' => $sohag->name,
                'slug' => $sohag->slug,
            ]);

            $this->command->info("Governorate created: Sohag (سوهاج)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


