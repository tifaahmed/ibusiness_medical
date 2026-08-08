<?php

namespace Database\Seeders\QalyubiaData;

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
            // Create Qalyubia governorate
            $qalyubia = Governorate::updateOrCreate(
                [
                    'slug' => 'qalyubia',
                ],
                [
                    'name' => [
                        'ar' => 'القليوبية',
                        'en' => 'Qalyubia',
                    ],
                ]
            );

            Log::info('Qalyubia governorate created/updated', [
                'id' => $qalyubia->id,
                'name' => $qalyubia->name,
                'slug' => $qalyubia->slug,
            ]);

            $this->command->info("Governorate created: Qalyubia (القليوبية)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


