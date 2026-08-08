<?php

namespace Database\Seeders\BeniSuefData;

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
            // Create Beni Suef governorate
            $beniSuef = Governorate::updateOrCreate(
                [
                    'slug' => 'beni-suef',
                ],
                [
                    'name' => [
                        'ar' => 'بني سويف',
                        'en' => 'Beni Suef',
                    ],
                ]
            );

            Log::info('Beni Suef governorate created/updated', [
                'id' => $beniSuef->id,
                'name' => $beniSuef->name,
                'slug' => $beniSuef->slug,
            ]);

            $this->command->info("Governorate created: Beni Suef (بني سويف)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


