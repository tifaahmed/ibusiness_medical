<?php

namespace Database\Seeders\QalyubiaData;

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

            $this->command->info("Starting branch import for Qalyubia...");

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

                    // Find the parent facility by slug
                    $facilitySlug = Str::slug($facilityData['name']['ar']);
                    $facility = Facility::where('slug', $facilitySlug)->first();

                    if (!$facility) {
                        continue;
                    }

                    // Process branches from the 'branches' array
                    $branches = $facilityData['branches'] ?? [];
                    
                    // Check if facility already has branches
                    $existingBranchesCount = FacilityBranch::where('facility_id', $facility->id)->count();
                    
                    // Create branches from parsed data if available
                    if (!empty($branches) && is_array($branches)) {
                        foreach ($branches as $branchIndex => $branchData) {
                            // Handle location - can be string or array (translatable)
                            $location = $branchData['location'] ?? null;
                            $locationAr = '';
                            $locationEn = '';
                            
                            if (is_array($location)) {
                                $locationAr = $location['ar'] ?? $location['en'] ?? '';
                                $locationEn = $location['en'] ?? $location['ar'] ?? '';
                            } elseif (is_string($location)) {
                                $locationAr = $location;
                                $locationEn = $location;
                            }
                            
                            // Generate unique slug for branch
                            $branchIdentifier = '';
                            
                            if (!empty($locationAr)) {
                                // Use location for identifier (Arabic version)
                                $locationParts = explode('-', $locationAr);
                                $branchIdentifier = Str::slug(trim($locationParts[0]));
                            } elseif (!empty($branchData['phone'])) {
                                // Handle phone - can be string or array
                                $phoneStr = is_array($branchData['phone']) 
                                    ? (reset($branchData['phone']) ?: '') 
                                    : (string) $branchData['phone'];
                                $branchIdentifier = 'phone-' . Str::slug($phoneStr);
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
                            if (!empty($locationAr)) {
                                if (mb_strlen($locationAr) > 60) {
                                    $branchNameAr = 'فرع ' . mb_substr($locationAr, 0, 50) . '...';
                                    $branchNameEn = 'Branch ' . mb_substr($locationEn, 0, 50) . '...';
                                } else {
                                    $branchNameAr = 'فرع ' . $locationAr;
                                    $branchNameEn = !empty($locationEn) ? 'Branch ' . $locationEn : 'Branch ' . ($branchIndex + 1);
                                }
                            } else {
                                $branchNameAr = 'فرع ' . ($branchIndex + 1);
                                $branchNameEn = 'Branch ' . ($branchIndex + 1);
                            }
                            
                            // Handle phone - normalize to array
                            $phone = $branchData['phone'] ?? null;
                            if (is_string($phone) && !empty($phone)) {
                                $phone = [trim($phone)];
                            } elseif (is_array($phone)) {
                                $phone = array_map('trim', array_filter($phone, fn($p) => !empty($p)));
                                $phone = !empty($phone) ? array_values($phone) : null;
                            } else {
                                $phone = null;
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
                                        'ar' => $locationAr,
                                        'en' => $locationEn ?: $locationAr,
                                    ],
                                    'phone' => $phone,
                                ]
                            );

                            if ($branch->wasRecentlyCreated) {
                                $created++;
                                $branchInfo = [];
                                if (!empty($locationAr)) $branchInfo[] = "Location: {$locationAr}";
                                if (!empty($phone)) {
                                    $phoneDisplay = is_array($phone) ? implode(', ', $phone) : $phone;
                                    $branchInfo[] = "Phone: {$phoneDisplay}";
                                }
                                $info = !empty($branchInfo) ? ' (' . implode(', ', $branchInfo) . ')' : '';
                                $this->command->info("  ✓ Created branch #" . ($branchIndex + 1) . " for facility: {$facilityData['name']['ar']}{$info}");
                            } else {
                                $updated++;
                                $this->command->line("  Updated branch #" . ($branchIndex + 1) . " for facility: {$facilityData['name']['ar']}");
                            }
                        }
                    }

                    // Also handle facilities with multiple phones but no branches array
                    // Create additional branches for extra phones
                    $phones = $facilityData['phones'] ?? [];
                    if (count($phones) > 1 && empty($branches)) {
                        $addressAr = $facilityData['address']['ar'] ?? '';
                        $addressEn = $facilityData['address']['en'] ?? $addressAr;
                        
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
                    // If no branches exist, create main branch using facility's data
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
            }

            $this->command->info("Branch import complete: {$created} created, {$updated} updated.");

        } catch (\Exception $e) {
            Log::error('Error importing facility branches: ' . $e->getMessage());
            $this->command->error('Error importing facility branches: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get JSON data from file
     */
    protected function getJsonData(): ?array
    {
        $jsonFilePath = base_path('database/seeders/QalyubiaData/qalyubia.json');

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


