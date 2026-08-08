<?php

namespace Database\Seeders\CairoGizaData;

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
            // Create Cairo governorate
            $cairo = Governorate::updateOrCreate(
                [
                    'slug' => 'cairo',
                ],
                [
                    'name' => [
                        'ar' => 'القاهرة',
                        'en' => 'Cairo',
                    ],
                ]
            );

            Log::info('Cairo governorate created/updated', [
                'id' => $cairo->id,
                'name' => $cairo->name,
                'slug' => $cairo->slug,
            ]);

            $this->command->info("Governorate created: Cairo (القاهرة)");

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
            Log::error('Error creating governorates: ' . $e->getMessage());
            $this->command->error('Error creating governorates: ' . $e->getMessage());
            throw $e;
        }
    }
}

