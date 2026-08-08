<?php

namespace Database\Seeders\GizaData;

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
            // Create Giza governorate
            $giza = Governorate::updateOrCreate(
                [
                    'slug' => 'giza',
                ],
                [
                    'name' => [
                        'ar' => 'الجيزة',
                        'en' => 'Giza',
                    ],
                ]
            );

            Log::info('Giza governorate created/updated', [
                'id' => $giza->id,
                'name' => $giza->name,
                'slug' => $giza->slug,
            ]);

            $this->command->info("Governorate created: Giza (الجيزة)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


