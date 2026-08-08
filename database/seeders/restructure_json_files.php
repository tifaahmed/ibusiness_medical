<?php

/**
 * Script to restructure all JSON files to move address and phones from facilities to branches
 * Every facility must have at least one branch
 */

function restructureFacility($facility) {
    // If facility already has branches, check if they need restructuring
    if (isset($facility['branches']) && is_array($facility['branches']) && !empty($facility['branches'])) {
        // Restructure existing branches to ensure they have location and phones/phone
        $restructuredBranches = [];
        foreach ($facility['branches'] as $branch) {
            $restructuredBranch = [];
            
            // Handle location - can be string or object
            if (isset($branch['location'])) {
                $restructuredBranch['location'] = $branch['location'];
            } elseif (isset($branch['address'])) {
                // Convert address to location
                $restructuredBranch['location'] = $branch['address'];
            }
            
            // Handle phones - prioritize phones array, then phone, then check facility level
            if (isset($branch['phones']) && is_array($branch['phones']) && !empty($branch['phones'])) {
                $restructuredBranch['phones'] = $branch['phones'];
            } elseif (isset($branch['phone'])) {
                $restructuredBranch['phone'] = $branch['phone'];
            } elseif (isset($facility['phones']) && is_array($facility['phones']) && !empty($facility['phones'])) {
                // If branch has no phone but facility has phones, use facility phones
                $restructuredBranch['phones'] = $facility['phones'];
            } elseif (isset($facility['hotline'])) {
                $restructuredBranch['phone'] = $facility['hotline'];
            }
            
            if (!empty($restructuredBranch)) {
                $restructuredBranches[] = $restructuredBranch;
            }
        }
        
        // Remove address and phones from facility level
        unset($facility['address']);
        unset($facility['phones']);
        unset($facility['phone']);
        unset($facility['hotline']);
        
        if (!empty($restructuredBranches)) {
            $facility['branches'] = $restructuredBranches;
        }
        
        return $facility;
    }
    
    // If facility has address or phones at facility level, convert to branch
    if (isset($facility['address']) || isset($facility['phones']) || isset($facility['phone']) || isset($facility['hotline'])) {
        $branch = [];
        
        if (isset($facility['address'])) {
            $branch['location'] = $facility['address'];
        }
        
        if (isset($facility['phones']) && is_array($facility['phones']) && !empty($facility['phones'])) {
            $branch['phones'] = $facility['phones'];
        } elseif (isset($facility['phone'])) {
            $branch['phone'] = $facility['phone'];
        } elseif (isset($facility['hotline'])) {
            $branch['phone'] = $facility['hotline'];
        }
        
        // Remove address and phones from facility level
        unset($facility['address']);
        unset($facility['phones']);
        unset($facility['phone']);
        unset($facility['hotline']);
        
        // Add branch
        if (!empty($branch)) {
            $facility['branches'] = [$branch];
        }
    }
    
    // Ensure facility has at least one branch
    if (!isset($facility['branches']) || empty($facility['branches'])) {
        // Create a default branch with empty location if no data available
        $facility['branches'] = [
            [
                "location" => [
                    "ar" => "",
                    "en" => ""
                ]
            ]
        ];
    }
    
    return $facility;
}

function restructureJsonFile($filePath) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data || !isset($data['medical_directory'])) {
        echo "Invalid JSON structure in: $filePath\n";
        return false;
    }
    
    $modified = false;
    
    // Process each category
    foreach ($data['medical_directory'] as &$category) {
        if (!isset($category['facilities']) || !is_array($category['facilities'])) {
            continue;
        }
        
        // Process each facility
        foreach ($category['facilities'] as &$facility) {
            $original = json_encode($facility);
            $facility = restructureFacility($facility);
            $new = json_encode($facility);
            
            if ($original !== $new) {
                $modified = true;
            }
        }
    }
    
    if ($modified) {
        $newContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $newContent);
        echo "✓ Restructured: $filePath\n";
        return true;
    } else {
        echo "- No changes needed: $filePath\n";
        return false;
    }
}

// Get all JSON files in seeders directory
$seedersDir = __DIR__;
$jsonFiles = glob($seedersDir . '/*/*.json');

if (empty($jsonFiles)) {
    echo "No JSON files found in seeders directory\n";
    exit(1);
}

echo "Found " . count($jsonFiles) . " JSON files\n";
echo "Restructuring files...\n\n";

$restructured = 0;
foreach ($jsonFiles as $file) {
    if (restructureJsonFile($file)) {
        $restructured++;
    }
}

echo "\n=== Summary ===\n";
echo "Total files: " . count($jsonFiles) . "\n";
echo "Restructured: $restructured\n";
echo "No changes needed: " . (count($jsonFiles) - $restructured) . "\n";

