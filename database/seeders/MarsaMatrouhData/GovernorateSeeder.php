<?php

namespace Database\Seeders\MarsaMatrouhData;

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
            // Create Marsa Matrouh governorate
            $marsaMatrouh = Governorate::updateOrCreate(
                [
                    'slug' => 'marsa-matrouh',
                ],
                [
                    'name' => [
                        'ar' => 'محافظة مرسى مطروح',
                        'en' => 'Marsa Matrouh Governorate',
                    ],
                ]
            );

            Log::info('Marsa Matrouh governorate created/updated', [
                'id' => $marsaMatrouh->id,
                'name' => $marsaMatrouh->name,
                'slug' => $marsaMatrouh->slug,
            ]);

            $this->command->info("Governorate created: Marsa Matrouh Governorate (محافظة مرسى مطروح)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


