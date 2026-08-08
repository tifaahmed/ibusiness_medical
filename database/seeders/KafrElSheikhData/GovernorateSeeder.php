<?php

namespace Database\Seeders\KafrElSheikhData;

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
            // Create Kafr El Sheikh governorate
            $kafrElSheikh = Governorate::updateOrCreate(
                [
                    'slug' => 'kafr-el-sheikh',
                ],
                [
                    'name' => [
                        'ar' => 'كفر الشيخ',
                        'en' => 'Kafr El Sheikh',
                    ],
                ]
            );

            Log::info('Kafr El Sheikh governorate created/updated', [
                'id' => $kafrElSheikh->id,
                'name' => $kafrElSheikh->name,
                'slug' => $kafrElSheikh->slug,
            ]);

            $this->command->info("Governorate created: Kafr El Sheikh (كفر الشيخ)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


