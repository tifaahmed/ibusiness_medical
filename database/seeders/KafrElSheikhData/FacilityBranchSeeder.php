<?php

namespace Database\Seeders\KafrElSheikhData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FacilityBranch;
use App\Models\Facility;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacilityBranchSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     * 
     * Creates branches for facilities from JSON data
     */
    public function run(): void
    {
        try {
            // Get JSON data
            $jsonData = $this->getJsonData();
            
            if (!$jsonData || empty($jsonData['medical_directory'])) {
                $this->command->warn('No medical directory found in JSON data.');
                return;
            }

            $this->command->info("Starting branch import for Kafr El Sheikh...");

            $created = 0;
            $updated = 0;

            foreach ($jsonData['medical_directory'] as $category) {
                if (empty($category['facilities'])) {
                    continue;
                }

                foreach ($category['facilities'] as $facilityData) {
                    if (empty($facilityData['name']['ar'])) {
                        continue;
                    }

                    $this->processFacilityBranches($facilityData, $created, $updated);
                }
            }

            $this->command->info("Branch import complete: {$created} created, {$updated} updated.");

        } catch (\Exception $e) {
            Log::error('Error importing facility branches: ' . $e->getMessage());
            $this->command->error('Error importing facility branches: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process branches for a facility
     */
    protected function processFacilityBranches(array $facilityData, &$created, &$updated): void
    {
        // Find the parent facility by slug
        $facilitySlug = Str::slug($facilityData['name']['ar']);
        $facility = Facility::where('slug', $facilitySlug)->first();

        if (!$facility) {
            return;
        }

        // Process branches from the 'branches' array
        $branches = $facilityData['branches'] ?? [];
        
        // Handle branches array (can be objects or strings)
        if (!empty($branches) && is_array($branches)) {
            foreach ($branches as $branchIndex => $branchData) {
                // Check if branch is a simple string
                if (is_string($branchData)) {
                    // Simple string location - create branch with location name
                    $location = $branchData;
                    $locationAr = $location;
                    $locationEn = $location;
                    
                    $branchSlug = Str::slug($facility->slug . '-' . Str::slug($location));
                    
                    // Get phone from facility's phones array or hotline
                    $branchPhone = null;
                    if (!empty($facilityData['phones']) && is_array($facilityData['phones'])) {
                        // Distribute phones across string branches if available
                        $phoneIndex = $branchIndex % count($facilityData['phones']);
                        $branchPhone = trim($facilityData['phones'][$phoneIndex]);
                    } elseif (!empty($facilityData['hotline'])) {
                        $branchPhone = trim($facilityData['hotline']);
                    }
                    
                    $branch = FacilityBranch::updateOrCreate(
                        [
                            'slug' => $branchSlug,
                        ],
                        [
                            'facility_id' => $facility->id,
                            'name' => [
                                'ar' => 'فرع ' . $location,
                                'en' => 'Branch ' . $location,
                            ],
                            'address' => [
                                'ar' => $location,
                                'en' => $location,
                            ],
                            'phone' => $branchPhone,
                        ]
                    );

                    if ($branch->wasRecentlyCreated) {
                        $created++;
                        $this->command->info("  ✓ Created branch: {$location} for facility: {$facilityData['name']['ar']}");
                    } else {
                        $updated++;
                    }
                } else {
                    // Branch is an object - handle normally
                    $this->createBranchFromObject($facility, $facilityData, $branchData, $branchIndex, $created, $updated);
                }
            }
        }

        // Also handle facilities with multiple phones but no branches array
        $phones = $facilityData['phones'] ?? [];
        if (empty($phones) && !empty($facilityData['hotline'])) {
            $phones = [$facilityData['hotline']];
        }
        
        if (count($phones) > 1 && empty($branches)) {
            $addressAr = (string) ($facilityData['address']['ar'] ?? '');
            $addressEn = (string) ($facilityData['address']['en'] ?? $addressAr);
            
            // First phone is already on the facility, create branches for remaining phones
            for ($i = 1; $i < count($phones); $i++) {
                $phone = trim($phones[$i]);
                if (empty($phone)) {
                    continue;
                }

                $branchSlug = Str::slug($facility->slug . '-phone-' . Str::slug($phone));
                
                FacilityBranch::updateOrCreate(
                    [
                        'slug' => $branchSlug,
                    ],
                    [
                        'facility_id' => $facility->id,
                        'name' => [
                            'ar' => 'فرع ' . ($i + 1),
                            'en' => 'Branch ' . ($i + 1),
                        ],
                        'address' => [
                            'ar' => $addressAr,
                            'en' => $addressEn,
                        ],
                        'phone' => $phone,
                    ]
                );
                
                $created++;
                $this->command->info("  ✓ Created branch #" . ($i + 1) . " for facility: {$facilityData['name']['ar']} (Phone: {$phone})");
            }
        }
        
        // ENSURE every facility has at least one branch
        $currentBranchesCount = FacilityBranch::where('facility_id', $facility->id)->count();
        
        if ($currentBranchesCount === 0) {
            // Create main branch using facility's data
            $branchSlug = Str::slug($facility->slug . '-main');
            
            // Get address and phone from facilityData (JSON), not from facility model
            $branchAddressAr = (string) ($facilityData['address']['ar'] ?? '');
            $branchAddressEn = (string) ($facilityData['address']['en'] ?? $branchAddressAr);
            
            // Handle phones - can be in 'phones' array or 'hotline' field
            $phones = $facilityData['phones'] ?? [];
            if (empty($phones) && !empty($facilityData['hotline'])) {
                $phones = [$facilityData['hotline']];
            }
            
            // Convert phones to array format
            $phoneArray = !empty($phones) ? array_map('trim', array_filter($phones, fn($p) => !empty($p))) : null;
            
            // Always create a branch - ensures every facility has at least one branch
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
                        'ar' => $branchAddressAr,
                        'en' => $branchAddressEn,
                    ],
                    'phone' => $phoneArray,
                ]
            );
            
            $created++;
            $branchInfo = [];
            if (!empty($branchAddressAr)) $branchInfo[] = "Address: {$branchAddressAr}";
            if (!empty($phones)) $branchInfo[] = "Phone: " . implode(', ', $phones);
            $info = !empty($branchInfo) ? ' (' . implode(', ', $branchInfo) . ')' : '';
            $this->command->info("  ✓ Created main branch for facility: {$facilityData['name']['ar']}{$info}");
        }
    }

    /**
     * Create branch from branch object
     */
    protected function createBranchFromObject($facility, array $facilityData, array $branchData, int $branchIndex, &$created, &$updated): void
    {
        // Handle location - can be string, object with ar/en, or in 'location' field
        $location = '';
        $locationAr = '';
        $locationEn = '';
        
        // Check for 'location' field (can be object with ar/en or string)
        if (!empty($branchData['location'])) {
            if (is_array($branchData['location'])) {
                // Location is an object with ar/en
                $locationAr = (string) ($branchData['location']['ar'] ?? '');
                $locationEn = (string) ($branchData['location']['en'] ?? $locationAr);
                $location = $locationAr; // Use Arabic for identifier
            } else {
                // Location is a string
                $location = (string) $branchData['location'];
                $locationAr = $location;
                $locationEn = $location;
            }
        }
        
        // Check for 'address' field (alternative format)
        if (empty($location) && !empty($branchData['address'])) {
            if (is_array($branchData['address'])) {
                $locationAr = (string) ($branchData['address']['ar'] ?? '');
                $locationEn = (string) ($branchData['address']['en'] ?? $locationAr);
                $location = $locationAr;
            } else {
                $location = (string) $branchData['address'];
                $locationAr = $location;
                $locationEn = $location;
            }
        }
        
        // Ensure we always have valid strings
        $locationAr = $locationAr ?: '';
        $locationEn = $locationEn ?: $locationAr;
        
        // Handle phones - branch can have 'phone' (single) or 'phones' (array)
        $branchPhones = [];
        if (!empty($branchData['phone'])) {
            $branchPhones = [trim($branchData['phone'])];
        } elseif (!empty($branchData['phones']) && is_array($branchData['phones'])) {
            $branchPhones = array_map('trim', $branchData['phones']);
        }
        
        // If branch has multiple phones, create a branch for each phone
        if (count($branchPhones) > 1) {
            foreach ($branchPhones as $phoneIndex => $phone) {
                if (empty($phone)) {
                    continue;
                }
                
                $branchIdentifier = '';
                if (!empty($location)) {
                    $locationParts = explode('-', $location);
                    $branchIdentifier = Str::slug(trim($locationParts[0]) . '-phone-' . ($phoneIndex + 1));
                } else {
                    $branchIdentifier = 'phone-' . Str::slug($phone);
                }
                
                // Limit identifier length
                if (strlen($branchIdentifier) > 50) {
                    $branchIdentifier = substr($branchIdentifier, 0, 50);
                }
                
                $branchSlug = Str::slug($facility->slug . '-' . $branchIdentifier);
                
                if (empty($branchSlug) || strlen($branchSlug) < 5) {
                    $branchSlug = Str::slug($facility->slug . '-branch-' . ($branchIndex + 1) . '-phone-' . ($phoneIndex + 1));
                }
                
                // Create branch name from location
                $branchNameAr = '';
                $branchNameEn = '';
                
                if (!empty($locationAr)) {
                    if (strlen($locationAr) > 60) {
                        $branchNameAr = 'فرع ' . substr($locationAr, 0, 50) . '...';
                    } else {
                        $branchNameAr = 'فرع ' . $locationAr;
                    }
                } else {
                    $branchNameAr = 'فرع ' . ($branchIndex + 1);
                }
                
                if (!empty($locationEn)) {
                    if (strlen($locationEn) > 60) {
                        $branchNameEn = 'Branch ' . substr($locationEn, 0, 50) . '...';
                    } else {
                        $branchNameEn = 'Branch ' . $locationEn;
                    }
                } else {
                    $branchNameEn = 'Branch ' . ($branchIndex + 1);
                }
                
                // Create or update branch
                $branch = FacilityBranch::updateOrCreate(
                    [
                        'slug' => $branchSlug,
                    ],
                    [
                        'facility_id' => $facility->id,
                        'name' => [
                            'ar' => $branchNameAr,
                            'en' => $branchNameEn,
                        ],
                        'address' => [
                            'ar' => (string) $locationAr,
                            'en' => (string) $locationEn,
                        ],
                        'phone' => $phone,
                    ]
                );

                if ($branch->wasRecentlyCreated) {
                    $created++;
                    $branchInfo = [];
                    if (!empty($location)) $branchInfo[] = "Location: {$location}";
                    if (!empty($phone)) $branchInfo[] = "Phone: " . $phone;
                    $info = !empty($branchInfo) ? ' (' . implode(', ', $branchInfo) . ')' : '';
                    $this->command->info("  ✓ Created branch #" . ($branchIndex + 1) . " (phone " . ($phoneIndex + 1) . ") for facility: {$facilityData['name']['ar']}{$info}");
                } else {
                    $updated++;
                }
            }
        } else {
            // Single phone or no phone - create one branch
            $branchPhone = !empty($branchPhones) ? $branchPhones[0] : null;
            
            // Use location for identifier
            $branchIdentifier = '';
            if (!empty($location)) {
                $locationParts = explode('-', $location);
                $branchIdentifier = Str::slug(trim($locationParts[0]));
            } elseif (!empty($branchPhone)) {
                $branchIdentifier = 'phone-' . Str::slug($branchPhone);
            } else {
                $branchIdentifier = 'branch-' . ($branchIndex + 1);
            }
            
            // Limit identifier length
            if (strlen($branchIdentifier) > 50) {
                $branchIdentifier = substr($branchIdentifier, 0, 50);
            }
            
            $branchSlug = Str::slug($facility->slug . '-' . $branchIdentifier);
            
            if (empty($branchSlug) || strlen($branchSlug) < 5) {
                $branchSlug = Str::slug($facility->slug . '-branch-' . ($branchIndex + 1));
            }
            
            // Create branch name from location
            $branchNameAr = '';
            $branchNameEn = '';
            
            if (!empty($locationAr)) {
                if (strlen($locationAr) > 60) {
                    $branchNameAr = 'فرع ' . substr($locationAr, 0, 50) . '...';
                } else {
                    $branchNameAr = 'فرع ' . $locationAr;
                }
            } else {
                $branchNameAr = 'فرع ' . ($branchIndex + 1);
            }
            
            if (!empty($locationEn)) {
                if (strlen($locationEn) > 60) {
                    $branchNameEn = 'Branch ' . substr($locationEn, 0, 50) . '...';
                } else {
                    $branchNameEn = 'Branch ' . $locationEn;
                }
            } else {
                $branchNameEn = 'Branch ' . ($branchIndex + 1);
            }
            
            // Get phone from branch data or use facility's hotline/phones
            if (empty($branchPhone)) {
                if (!empty($facilityData['hotline'])) {
                    $branchPhone = trim($facilityData['hotline']);
                } elseif (!empty($facilityData['phones']) && is_array($facilityData['phones'])) {
                    $branchPhone = trim($facilityData['phones'][0]);
                }
            }
            
            // Create or update branch
            $branch = FacilityBranch::updateOrCreate(
                [
                    'slug' => $branchSlug,
                ],
                [
                    'facility_id' => $facility->id,
                    'name' => [
                        'ar' => $branchNameAr,
                        'en' => $branchNameEn,
                    ],
                    'address' => [
                        'ar' => (string) $locationAr,
                        'en' => (string) $locationEn,
                    ],
                    'phone' => $branchPhone,
                ]
            );

            if ($branch->wasRecentlyCreated) {
                $created++;
                $branchInfo = [];
                if (!empty($location)) $branchInfo[] = "Location: {$location}";
                if (!empty($branchPhone)) $branchInfo[] = "Phone: " . $branchPhone;
                $info = !empty($branchInfo) ? ' (' . implode(', ', $branchInfo) . ')' : '';
                $this->command->info("  ✓ Created branch #" . ($branchIndex + 1) . " for facility: {$facilityData['name']['ar']}{$info}");
            } else {
                $updated++;
                $this->command->line("  Updated branch #" . ($branchIndex + 1) . " for facility: {$facilityData['name']['ar']}");
            }
        }
    }

    /**
     * Get JSON data from file
     */
    protected function getJsonData(): ?array
    {
        $jsonFilePath = base_path('database/seeders/KafrElSheikhData/kafr-el-sheikh.json');

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

