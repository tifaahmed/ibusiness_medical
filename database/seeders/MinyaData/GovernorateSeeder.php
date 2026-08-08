<?php

namespace Database\Seeders\MinyaData;

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
            // Create Minya governorate
            $minya = Governorate::updateOrCreate(
                [
                    'slug' => 'minya',
                ],
                [
                    'name' => [
                        'ar' => 'المنيا',
                        'en' => 'Minya',
                    ],
                ]
            );

            Log::info('Minya governorate created/updated', [
                'id' => $minya->id,
                'name' => $minya->name,
                'slug' => $minya->slug,
            ]);

            $this->command->info("Governorate created: Minya (المنيا)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


