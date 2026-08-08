<?php

namespace Database\Seeders\RedSeaData;

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
            // Create Red Sea governorate
            $redSea = Governorate::updateOrCreate(
                [
                    'slug' => 'red-sea',
                ],
                [
                    'name' => [
                        'ar' => 'البحر الأحمر',
                        'en' => 'Red Sea',
                    ],
                ]
            );

            Log::info('Red Sea governorate created/updated', [
                'id' => $redSea->id,
                'name' => $redSea->name,
                'slug' => $redSea->slug,
            ]);

            $this->command->info("Governorate created: Red Sea (البحر الأحمر)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


