<?php

namespace Database\Seeders\CairoData;

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
                        'ar' => 'محافظة القاهرة',
                        'en' => 'Cairo Governorate',
                    ],
                ]
            );

            Log::info('Cairo governorate created/updated', [
                'id' => $cairo->id,
                'name' => $cairo->name,
                'slug' => $cairo->slug,
            ]);

            $this->command->info("Governorate created: Cairo Governorate (محافظة القاهرة)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


