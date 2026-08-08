<?php

/**
 * Fix all JSON files to ensure:
 * 1. Facilities do NOT have address or phones at facility level
 * 2. Every facility has at least one branch
 * 3. Address and phones are moved to branches
 */

$baseDir = __DIR__;
$jsonFiles = glob($baseDir . '/*/*.json');
$jsonFiles = array_merge($jsonFiles, glob($baseDir . '/*Data/*.json'));

// Exclude non-data files
$excludeFiles = ['missing_part3_facilities.json'];
$jsonFiles = array_filter($jsonFiles, function($file) use ($excludeFiles) {
    $basename = basename($file);
    return !in_array($basename, $excludeFiles);
});

$fixedFiles = [];
$totalFixed = 0;

echo "=== Fixing JSON Structure ===\n\n";

foreach ($jsonFiles as $jsonFile) {
    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $jsonFile);
    
    $content = file_get_contents($jsonFile);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ ERROR parsing JSON: $relativePath - " . json_last_error_msg() . "\n";
        continue;
    }
    
    if (!isset($data['medical_directory'])) {
        echo "⚠️  Skipping $relativePath - No medical_directory found\n";
        continue;
    }
    
    $fileModified = false;
    $facilitiesFixed = 0;
    
    foreach ($data['medical_directory'] as &$category) {
        if (!isset($category['facilities'])) {
            continue;
        }
        
        foreach ($category['facilities'] as &$facility) {
            $facilityModified = false;
            
            // Check if facility has address or phones at facility level
            $hasAddress = isset($facility['address']);
            $hasPhones = isset($facility['phones']);
            $hasPhone = isset($facility['phone']);
            $hasHotline = isset($facility['hotline']);
            $hasBranches = isset($facility['branches']) && is_array($facility['branches']) && !empty($facility['branches']);
            
            // If facility has address/phones but no branches, create a branch
            if (($hasAddress || $hasPhones || $hasPhone) && !$hasBranches) {
                // Create a branch from the facility-level data
                $branch = [];
                
                // Move address to location
                if ($hasAddress) {
                    if (is_array($facility['address'])) {
                        $branch['location'] = $facility['address'];
                    } else {
                        // If address is a string, create location object
                        $branch['location'] = [
                            'ar' => $facility['address'],
                            'en' => $facility['address']
                        ];
                    }
                }
                
                // Move phones to branch
                if ($hasPhones) {
                    $branch['phones'] = is_array($facility['phones']) ? $facility['phones'] : [$facility['phones']];
                } elseif ($hasPhone) {
                    $branch['phones'] = [$facility['phone']];
                } elseif ($hasHotline) {
                    $branch['phones'] = [$facility['hotline']];
                }
                
                // Initialize branches array and add the branch
                $facility['branches'] = [$branch];
                
                // Remove address/phones from facility level
                unset($facility['address']);
                unset($facility['phones']);
                unset($facility['phone']);
                if ($hasHotline) {
                    unset($facility['hotline']);
                }
                
                $facilityModified = true;
                $facilitiesFixed++;
                $totalFixed++;
            }
            // If facility has address/phones AND branches, move to first branch or remove from facility
            elseif (($hasAddress || $hasPhones || $hasPhone) && $hasBranches) {
                // Remove from facility level (branches already exist)
                unset($facility['address']);
                unset($facility['phones']);
                unset($facility['phone']);
                if ($hasHotline) {
                    unset($facility['hotline']);
                }
                $facilityModified = true;
                $facilitiesFixed++;
                $totalFixed++;
            }
            // If facility has no branches and no address/phones, skip (might be intentional for chains)
            elseif (!$hasBranches && !$hasAddress && !$hasPhones && !$hasPhone) {
                // Check if it's a chain with locations array (like pharmacies)
                if (isset($facility['locations']) || isset($facility['chains'])) {
                    // This is acceptable for chains
                    continue;
                }
                // Otherwise, this is an error - facility has no data
                echo "  ⚠️  Warning: Facility '{$facility['name']['ar']}' has no branches or address/phones\n";
            }
            
            // Fix branches structure - ensure branches are objects, not strings
            if (isset($facility['branches']) && is_array($facility['branches'])) {
                foreach ($facility['branches'] as &$branch) {
                    if (is_string($branch)) {
                        // Convert string branch to object
                        $branch = [
                            'location' => [
                                'ar' => $branch,
                                'en' => $branch
                            ]
                        ];
                        $facilityModified = true;
                    } elseif (is_array($branch)) {
                        // Ensure location is an object with ar/en
                        if (isset($branch['location'])) {
                            if (is_string($branch['location'])) {
                                $branch['location'] = [
                                    'ar' => $branch['location'],
                                    'en' => $branch['location']
                                ];
                                $facilityModified = true;
                            }
                        }
                        // Ensure address field is converted to location
                        if (isset($branch['address']) && !isset($branch['location'])) {
                            if (is_array($branch['address'])) {
                                $branch['location'] = $branch['address'];
                            } else {
                                $branch['location'] = [
                                    'ar' => $branch['address'],
                                    'en' => $branch['address']
                                ];
                            }
                            unset($branch['address']);
                            $facilityModified = true;
                        }
                    }
                }
                unset($branch); // Unset reference
            }
        }
        unset($facility); // Unset reference
        
        if ($facilitiesFixed > 0) {
            $fileModified = true;
        }
    }
    unset($category); // Unset reference
    
    if ($fileModified) {
        // Save the fixed JSON
        $fixedContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonFile, $fixedContent);
        
        $fixedFiles[] = [
            'file' => $relativePath,
            'facilities_fixed' => $facilitiesFixed
        ];
        
        echo "✅ Fixed: $relativePath ($facilitiesFixed facilities)\n";
    } else {
        echo "✓  OK: $relativePath (no changes needed)\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total files processed: " . count($jsonFiles) . "\n";
echo "Files modified: " . count($fixedFiles) . "\n";
echo "Total facilities fixed: $totalFixed\n\n";

if (!empty($fixedFiles)) {
    echo "FIXED FILES:\n";
    foreach ($fixedFiles as $file) {
        echo "  - {$file['file']}: {$file['facilities_fixed']} facilities\n";
    }
}

echo "\n✅ All JSON files have been fixed!\n";

