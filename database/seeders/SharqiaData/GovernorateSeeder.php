<?php

namespace Database\Seeders\SharqiaData;

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
            // Create Sharqia governorate
            $sharqia = Governorate::updateOrCreate(
                [
                    'slug' => 'sharqia',
                ],
                [
                    'name' => [
                        'ar' => 'الشرقية',
                        'en' => 'Sharqia',
                    ],
                ]
            );

            Log::info('Sharqia governorate created/updated', [
                'id' => $sharqia->id,
                'name' => $sharqia->name,
                'slug' => $sharqia->slug,
            ]);

            $this->command->info("Governorate created: Sharqia (الشرقية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


