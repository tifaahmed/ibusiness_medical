<?php

/**
 * Script to fix all FacilitySeeder files to remove address and phone from facilities
 * and ensure they're only in branches
 */

function fixFacilitySeeder($filePath) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Pattern 1: Remove address and phone from Facility::updateOrCreate
    // Match: 'address' => [...], 'phone' => ...,
    $content = preg_replace(
        "/\s*'address'\s*=>\s*\[[^\]]*\],?\s*/",
        "",
        $content
    );
    
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*[^,)]+,?\s*/",
        "",
        $content
    );
    
    // Pattern 2: Remove address and phone variable assignments before Facility::updateOrCreate
    // But keep them if they're used for creating branches
    // This is more complex, so we'll handle it case by case
    
    // Pattern 3: Fix FacilityBranchSeeder to get address/phone from facilityData instead of facility model
    if (strpos($filePath, 'FacilityBranchSeeder.php') !== false) {
        // Replace: $facility->address with $facilityData['address']
        $content = preg_replace(
            "/\$facility->address/",
            "\$facilityData['address']",
            $content
        );
        
        // Replace: $facility->phone with $facilityData['phone'] or phones
        $content = preg_replace(
            "/\$facility->phone/",
            "(\$facilityData['phones'][0] ?? \$facilityData['hotline'] ?? null)",
            $content
        );
        
        // Fix the pattern where it reads from facility model
        $content = preg_replace(
            "/\/\/ Get address and phone from facility\s*\$facilityAddress = \$facility->address;.*?\$branchPhone = [^;]+;/s",
            "// Get address and phone from facilityData (JSON), not from facility model\n            \$branchAddressAr = (string) (\$facilityData['address']['ar'] ?? '');\n            \$branchAddressEn = (string) (\$facilityData['address']['en'] ?? \$branchAddressAr);\n            \n            // Handle phones - can be in 'phones' array or 'hotline' field\n            \$phones = \$facilityData['phones'] ?? [];\n            if (empty(\$phones) && !empty(\$facilityData['hotline'])) {\n                \$phones = [\$facilityData['hotline']];\n            }\n            \n            // Convert phones to array format\n            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;",
            $content
        );
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✓ Fixed: $filePath\n";
        return true;
    } else {
        echo "- No changes needed: $filePath\n";
        return false;
    }
}

// Get all FacilitySeeder files
$seedersDir = __DIR__;
$facilitySeederFiles = glob($seedersDir . '/*/FacilitySeeder.php');
$facilityBranchSeederFiles = glob($seedersDir . '/*/FacilityBranchSeeder.php');

$allFiles = array_merge($facilitySeederFiles, $facilityBranchSeederFiles);

if (empty($allFiles)) {
    echo "No FacilitySeeder files found\n";
    exit(1);
}

echo "Found " . count($allFiles) . " FacilitySeeder files\n";
echo "Fixing files...\n\n";

$fixed = 0;
foreach ($allFiles as $file) {
    if (fixFacilitySeeder($file)) {
        $fixed++;
    }
}

echo "\n=== Summary ===\n";
echo "Total files: " . count($allFiles) . "\n";
echo "Fixed: $fixed\n";
echo "No changes needed: " . (count($allFiles) - $fixed) . "\n";


