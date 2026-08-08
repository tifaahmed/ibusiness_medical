<?php

namespace Database\Seeders\CairoGizaData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Governorate;
use App\Models\FacilityType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    use WithoutModelEvents;

    protected $cairo;
    protected $giza;
    protected $facilityTypes;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Get Cairo and Giza governorates
            $this->cairo = Governorate::where('slug', 'cairo')->first();
            $this->giza = Governorate::where('slug', 'giza')->first();
            
            if (!$this->cairo || !$this->giza) {
                $this->command->error('Cairo or Giza governorate not found. Please run GovernorateSeeder first.');
                return;
            }

            // Load facility types into cache
            $this->facilityTypes = FacilityType::all()->keyBy('slug');

            // Get JSON data
            $jsonData = $this->getJsonData();
            
            if (!$jsonData || empty($jsonData['medical_directory'])) {
                $this->command->warn('No medical directory found in JSON data.');
                return;
            }

            $this->command->info("Starting facility import for Cairo/Giza...");

            $created = 0;
            $updated = 0;

            foreach ($jsonData['medical_directory'] as $category) {
                if (empty($category['category']) || empty($category['facilities'])) {
                    continue;
                }

                // Get facility type for this category
                $categoryNameEn = $category['category']['en'] ?? '';
                $facilityTypeSlug = Str::slug($categoryNameEn);
                $facilityType = $this->facilityTypes->get($facilityTypeSlug);

                // If not found, try to find by Arabic name
                if (!$facilityType) {
                    $categoryNameAr = $category['category']['ar'] ?? '';
                    $facilityType = $this->facilityTypes->first(function ($type) use ($categoryNameAr) {
                        return $type->getTranslation('name', 'ar') === $categoryNameAr;
                    });
                }

                // Use a default facility type if none found
                if (!$facilityType) {
                    $facilityType = FacilityType::where('slug', 'medical-center')->first();
                    if (!$facilityType) {
                        $facilityType = FacilityType::first();
                    }
                }

                if (!$facilityType) {
                    $this->command->warn("No facility type available for category: {$categoryNameEn}");
                    continue;
                }

                $this->command->info("Processing category: {$categoryNameEn}");

                // Process facilities in this category
                foreach ($category['facilities'] as $facilityData) {
                    if (empty($facilityData['name']['ar'])) {
                        continue;
                    }

                    // Generate slug from Arabic name
                    $slug = Str::slug($facilityData['name']['ar']);
                    
                    if (empty($slug)) {
                        $this->command->warn("Skipping facility with empty slug: {$facilityData['name']['ar']}");
                        continue;
                    }

                    // Determine governorate - check if facility has explicit governorate field
                    $governorate = null;
                    if (!empty($facilityData['governorate'])) {
                        $governorateNameAr = $facilityData['governorate']['ar'] ?? '';
                        if ($governorateNameAr === 'القاهرة' || $governorateNameAr === 'Cairo') {
                            $governorate = $this->cairo;
                        } elseif ($governorateNameAr === 'الجيزة' || $governorateNameAr === 'Giza') {
                            $governorate = $this->giza;
                        }
                    }
                    
                    // If no explicit governorate, try to infer from address
                    if (!$governorate) {
                        $addressAr = $facilityData['address']['ar'] ?? '';
                        $addressEn = $facilityData['address']['en'] ?? '';
                        
                        // Check if address mentions Giza
                        if (stripos($addressAr, 'الجيزة') !== false || 
                            stripos($addressEn, 'Giza') !== false ||
                            stripos($addressAr, '6 أكتوبر') !== false ||
                            stripos($addressEn, '6th of October') !== false ||
                            stripos($addressAr, 'الهرم') !== false ||
                            stripos($addressEn, 'Haram') !== false ||
                            stripos($addressAr, 'الكيت كات') !== false ||
                            stripos($addressEn, 'Kit Kat') !== false) {
                            $governorate = $this->giza;
                        } elseif (stripos($addressAr, 'القاهرة') !== false || 
                                  stripos($addressEn, 'Cairo') !== false ||
                                  stripos($addressAr, 'مدينة نصر') !== false ||
                                  stripos($addressEn, 'Nasr City') !== false ||
                                  stripos($addressAr, 'المعادى') !== false ||
                                  stripos($addressEn, 'Maadi') !== false ||
                                  stripos($addressAr, 'شبرا') !== false ||
                                  stripos($addressEn, 'Shubra') !== false ||
                                  stripos($addressAr, 'روكسى') !== false ||
                                  stripos($addressEn, 'Roxy') !== false ||
                                  stripos($addressAr, 'المهندسين') !== false ||
                                  stripos($addressEn, 'Mohandessin') !== false ||
                                  stripos($addressAr, 'فيصل') !== false ||
                                  stripos($addressEn, 'Faisal') !== false ||
                                  stripos($addressAr, 'المطرية') !== false ||
                                  stripos($addressEn, 'Matareya') !== false ||
                                  stripos($addressAr, 'العجوزة') !== false ||
                                  stripos($addressEn, 'Agouza') !== false) {
                            // Address mentions Cairo-specific locations
                            $governorate = $this->cairo;
                        } else {
                            // Default to Cairo if unclear
                            $governorate = $this->cairo;
                        }
                    }

                    // Get address and phones
                    $addressAr = $facilityData['address']['ar'] ?? '';
                    $addressEn = $facilityData['address']['en'] ?? $addressAr;
                    
                    // Handle phones - can be in 'phones' array or 'hotline' field
                    $phones = $facilityData['phones'] ?? [];
                    if (empty($phones) && !empty($facilityData['hotline'])) {
                        $phones = [$facilityData['hotline']];
                    }
                    
                    $firstPhone = !empty($phones) ? trim($phones[0]) : null;

                    // Create or update facility (without address and phone - those go in branches)
                    $facility = Facility::updateOrCreate(
                        [
                            'slug' => $slug,
                        ],
                        [
                            'name' => [
                                'ar' => $facilityData['name']['ar'],
                                'en' => $facilityData['name']['en'] ?? $facilityData['name']['ar'],
                            ],
                            'facility_type_id' => $facilityType->id,
                            'governorate_id' => $governorate->id,
                        ]
                    );

                    if ($facility->wasRecentlyCreated) {
                        $created++;
                        $this->command->info("  ✓ Created facility: {$facilityData['name']['ar']} ({$governorate->getTranslation('name', 'en')})");
                    } else {
                        $updated++;
                        $this->command->line("  Updated facility: {$facilityData['name']['ar']}");
                    }

                    // Store branches data in facility for later processing
                    // If facility has branches array, we'll handle it in FacilityBranchSeeder
                    // Otherwise, create first branch from facility data if available
                    $hasBranches = !empty($facilityData['branches']) && is_array($facilityData['branches']);
                    
                    if (!$hasBranches) {
                        // Ensure facility has at least one branch from its own data
                        $existingBranchesCount = FacilityBranch::where('facility_id', $facility->id)->count();
                        
                        if ($existingBranchesCount === 0 && (!empty($addressAr) || !empty($phones))) {
                            // Create main branch from facility data
                            $branchSlug = Str::slug($facility->slug . '-main');
                            
                            // Convert phones to array format
                            $phoneArray = !empty($phones) ? array_map('trim', array_filter($phones, fn($p) => !empty($p))) : null;
                            
                            FacilityBranch::updateOrCreate(
                                [
                                    'slug' => $branchSlug,
                                ],
                                [
                                    'facility_id' => $facility->id,
                                    'name' => [
                                        'ar' => 'الفرع الرئيسي',
                                        'en' => 'Main Branch',
                                    ],
                                    'address' => [
                                        'ar' => $addressAr,
                                        'en' => $addressEn,
                                    ],
                                    'phone' => $phoneArray,
                                ]
                            );
                            
                            $this->command->line("    Created main branch");
                        }
                    }

                    Log::info('Facility created/updated', [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'slug' => $facility->slug,
                        'governorate' => $governorate->slug,
                        'has_branches' => $hasBranches,
                    ]);
                }
            }

            $this->command->info("Facility import complete: {$created} created, {$updated} updated.");

        } catch (\Exception $e) {
            Log::error('Error importing facilities: ' . $e->getMessage());
            $this->command->error('Error importing facilities: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get JSON data from file
     */
    protected function getJsonData(): ?array
    {
        $jsonFilePath = base_path('database/seeders/CairoGizaData/cairo-giza.json');

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

