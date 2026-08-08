<?php

namespace Database\Seeders\QenaData;

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
            // Create Qena governorate
            $qena = Governorate::updateOrCreate(
                [
                    'slug' => 'qena',
                ],
                [
                    'name' => [
                        'ar' => 'قنا',
                        'en' => 'Qena',
                    ],
                ]
            );

            Log::info('Qena governorate created/updated', [
                'id' => $qena->id,
                'name' => $qena->name,
                'slug' => $qena->slug,
            ]);

            $this->command->info("Governorate created: Qena (قنا)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


