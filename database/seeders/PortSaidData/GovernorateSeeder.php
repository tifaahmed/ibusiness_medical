<?php

namespace Database\Seeders\PortSaidData;

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
            // Create Port Said governorate
            $portSaid = Governorate::updateOrCreate(
                [
                    'slug' => 'port-said',
                ],
                [
                    'name' => [
                        'ar' => 'بورسعيد',
                        'en' => 'Port Said',
                    ],
                ]
            );

            Log::info('Port Said governorate created/updated', [
                'id' => $portSaid->id,
                'name' => $portSaid->name,
                'slug' => $portSaid->slug,
            ]);

            $this->command->info("Governorate created: Port Said (بورسعيد)");

        } catch (\Exception $e) {
            Log::error('Error creating governorate: ' . $e->getMessage());
            $this->command->error('Error creating governorate: ' . $e->getMessage());
            throw $e;
        }
    }
}


