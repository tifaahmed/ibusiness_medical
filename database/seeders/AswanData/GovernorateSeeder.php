<?php

namespace Database\Seeders\AswanData;

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
            // Create Aswan governorate
            $aswan = Governorate::updateOrCreate(
                [
                    'slug' => 'aswan',
                ],
                [
                    'name' => [
                        'ar' => 'أسوان',
                        'en' => 'Aswan',
                    ],
                ]
            );

            Log::info('Aswan governorate created/updated', [
                'id' => $aswan->id,
                'name' => $aswan->name,
                'slug' => $aswan->slug,
            ]);

            $this->command->info("Governorate created: Aswan (أسوان)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


