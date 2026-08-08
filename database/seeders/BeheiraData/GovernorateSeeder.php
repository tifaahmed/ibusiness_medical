<?php

namespace Database\Seeders\BeheiraData;

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
            // Create Beheira governorate
            $beheira = Governorate::updateOrCreate(
                [
                    'slug' => 'beheira',
                ],
                [
                    'name' => [
                        'ar' => 'البحيرة',
                        'en' => 'Beheira',
                    ],
                ]
            );

            Log::info('Beheira governorate created/updated', [
                'id' => $beheira->id,
                'name' => $beheira->name,
                'slug' => $beheira->slug,
            ]);

            $this->command->info("Governorate created: Beheira (البحيرة)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


