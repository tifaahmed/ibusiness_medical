<?php

namespace Database\Seeders\RedSeaData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FacilityType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacilityTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Get facility types from JSON data
            $jsonData = $this->getJsonData();
            
            if (!$jsonData || empty($jsonData['medical_directory'])) {
                $this->command->warn('No medical directory found in JSON data.');
                return;
            }

            $created = 0;
            $updated = 0;

            foreach ($jsonData['medical_directory'] as $category) {
                if (empty($category['category'])) {
                    continue;
                }

                $nameAr = $category['category']['ar'] ?? '';
                $nameEn = $category['category']['en'] ?? '';

                if (empty($nameAr)) {
                    continue;
                }

                // Generate slug from English name, fallback to Arabic
                $slug = Str::slug($nameEn);
                if (empty($slug)) {
                    $slug = Str::slug($nameAr);
                }

                if (empty($slug)) {
                    $this->command->warn("Skipping facility type with empty slug: {$nameAr}");
                    continue;
                }

                $facilityType = FacilityType::updateOrCreate(
                    [
                        'slug' => $slug,
                    ],
                    [
                        'name' => [
                            'ar' => $nameAr,
                            'en' => $nameEn,
                        ],
                    ]
                );

                if ($facilityType->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                Log::info('Facility type created/updated', [
                    'id' => $facilityType->id,
                    'name' => $facilityType->name,
                    'slug' => $facilityType->slug,
                ]);

                $this->command->info("Facility type: {$nameEn} ({$nameAr})");
            }

            $this->command->info("Facility type import complete: {$created} created, {$updated} updated.");

        } catch (\Exception $e) {
            Log::error('Error creating facility types: ' . $e->getMessage());
            $this->command->error('Error creating facility types: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get JSON data from file
     */
    protected function getJsonData(): ?array
    {
        $jsonFilePath = base_path('database/seeders/RedSeaData/red-sea.json');

        if (!file_exists($jsonFilePath)) {
            $this->command->warn('JSON file not found: ' . $jsonFilePath);
            return null;
        }

        $jsonContent = file_get_contents($jsonFilePath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Error parsing JSON: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }
}


