<?php

/**
 * Script to fix all FacilitySeeder files to remove address and phone from facilities
 * Run this script to fix all seeders at once
 */

$seedersDir = __DIR__;
$facilitySeederFiles = glob($seedersDir . '/*/FacilitySeeder.php');

if (empty($facilitySeederFiles)) {
    echo "No FacilitySeeder files found\n";
    exit(1);
}

echo "Found " . count($facilitySeederFiles) . " FacilitySeeder files\n";
echo "Fixing files...\n\n";

$fixed = 0;

foreach ($facilitySeederFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Pattern: Remove 'address' => [...] and 'phone' => ... from Facility::updateOrCreate
    // This regex matches the address and phone lines in the updateOrCreate array
    $content = preg_replace(
        "/\s*'address'\s*=>\s*\[[^\]]*\],?\s*/",
        "",
        $content
    );
    
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*\$[^,)]+,?\s*/",
        "",
        $content
    );
    
    // Also remove 'phone' => null,
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*null,?\s*/",
        "",
        $content
    );
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Fixed: $file\n";
        $fixed++;
    } else {
        echo "- No changes needed: $file\n";
    }
}

// Fix FacilityBranchSeeder files
$facilityBranchSeederFiles = glob($seedersDir . '/*/FacilityBranchSeeder.php');

echo "\nFound " . count($facilityBranchSeederFiles) . " FacilityBranchSeeder files\n";
echo "Fixing files...\n\n";

foreach ($facilityBranchSeederFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Replace: Get address and phone from facility -> Get from facilityData
    $content = preg_replace(
        "/\/\/ Get address and phone from facility\s*\$facilityAddress = \$facility->address;.*?\$branchPhone = [^;]+;/s",
        "// Get address and phone from facilityData (JSON), not from facility model\n            \$branchAddressAr = (string) (\$facilityData['address']['ar'] ?? '');\n            \$branchAddressEn = (string) (\$facilityData['address']['en'] ?? \$branchAddressAr);\n            \n            // Handle phones - can be in 'phones' array or 'hotline' field\n            \$phones = \$facilityData['phones'] ?? [];\n            if (empty(\$phones) && !empty(\$facilityData['hotline'])) {\n                \$phones = [\$facilityData['hotline']];\n            }\n            \n            // Convert phones to array format\n            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;",
        $content
    );
    
    // Replace: $branchPhone with $phoneArray in the updateOrCreate
    $content = preg_replace(
        "/'phone'\s*=>\s*\$branchPhone/",
        "'phone' => \$phoneArray",
        $content
    );
    
    // Replace: $branchAddressAr and $branchAddressEn references
    $content = preg_replace(
        "/\$branchAddressAr = \(string\) \(is_array\(\$facilityAddress\) \? \(\$facilityAddress\['ar'\] \?\? ''\) : ''\);/",
        "\$branchAddressAr = (string) (\$facilityData['address']['ar'] ?? '');",
        $content
    );
    
    $content = preg_replace(
        "/\$branchAddressEn = \(string\) \(is_array\(\$facilityAddress\) \? \(\$facilityAddress\['en'\] \?\? \$branchAddressAr\) : \$branchAddressAr\);/",
        "\$branchAddressEn = (string) (\$facilityData['address']['en'] ?? \$branchAddressAr);",
        $content
    );
    
    // Fix branch info messages
    $content = preg_replace(
        "/if \(!empty\(\$branchPhone\)\) \$branchInfo\[\] = \"Phone: \{\$branchPhone\}\";/",
        "if (!empty(\$phones)) \$branchInfo[] = \"Phone: \" . implode(', ', \$phones);",
        $content
    );
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Fixed: $file\n";
        $fixed++;
    } else {
        echo "- No changes needed: $file\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total FacilitySeeder files: " . count($facilitySeederFiles) . "\n";
echo "Total FacilityBranchSeeder files: " . count($facilityBranchSeederFiles) . "\n";
echo "Total fixed: $fixed\n";


