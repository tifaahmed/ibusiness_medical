<?php

namespace Database\Seeders\DamiettaData;

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
            // Create Damietta governorate
            $damietta = Governorate::updateOrCreate(
                [
                    'slug' => 'damietta',
                ],
                [
                    'name' => [
                        'ar' => 'دمياط',
                        'en' => 'Damietta',
                    ],
                ]
            );

            Log::info('Damietta governorate created/updated', [
                'id' => $damietta->id,
                'name' => $damietta->name,
                'slug' => $damietta->slug,
            ]);

            $this->command->info("Governorate created: Damietta (دمياط)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


