<?php

/**
 * Script to fix ALL FacilityBranchSeeder files
 * Replaces: Get address and phone from facility -> Get from facilityData
 */

$seedersDir = __DIR__;
$facilityBranchSeederFiles = glob($seedersDir . '/*/FacilityBranchSeeder.php');

if (empty($facilityBranchSeederFiles)) {
    echo "No FacilityBranchSeeder files found\n";
    exit(1);
}

echo "Found " . count($facilityBranchSeederFiles) . " FacilityBranchSeeder files\n";
echo "Fixing files...\n\n";

$fixed = 0;

foreach ($facilityBranchSeederFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Pattern: Replace "Get address and phone from facility" section
    $pattern = "/\/\/ Get address and phone from facility\s*\$facilityAddress = \$facility->address;.*?\$branchPhone = [^;]+;/s";
    $replacement = "// Get address and phone from facilityData (JSON), not from facility model\n            \$branchAddressAr = (string) (\$facilityData['address']['ar'] ?? '');\n            \$branchAddressEn = (string) (\$facilityData['address']['en'] ?? \$branchAddressAr);\n            \n            // Handle phones - can be in 'phones' array or 'hotline' field\n            \$phones = \$facilityData['phones'] ?? [];\n            if (empty(\$phones) && !empty(\$facilityData['hotline'])) {\n                \$phones = [\$facilityData['hotline']];\n            }\n            \n            // Convert phones to array format\n            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;";
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Replace: $branchPhone with $phoneArray
    $content = str_replace("'phone' => \$branchPhone,", "'phone' => \$phoneArray,", $content);
    $content = str_replace("'phone' => \$branchPhone", "'phone' => \$phoneArray", $content);
    
    // Fix branch info messages
    $content = preg_replace(
        "/if \(!empty\(\$branchPhone\)\) \$branchInfo\[\] = \"Phone: \{\$branchPhone\}\";/",
        "if (!empty(\$phones)) \$branchInfo[] = \"Phone: \" . implode(', ', \$phones);",
        $content
    );
    
    // Also handle cases where it might be checking $branchPhone differently
    $content = str_replace("\$branchPhone", "\$phoneArray", $content);
    
    // But we need to be careful - only replace in the context we want
    // Let's revert the last replacement and be more specific
    $content = str_replace("\$phoneArray", "\$branchPhone", $content);
    
    // Now do targeted replacements
    $content = str_replace("'phone' => \$branchPhone,", "'phone' => \$phoneArray,", $content);
    $content = str_replace("'phone' => \$branchPhone", "'phone' => \$phoneArray", $content);
    
    // Fix the branch info again
    $content = preg_replace(
        "/if \(!empty\(\$branchPhone\)\) \$branchInfo\[\] = \"Phone: \{\$branchPhone\}\";/",
        "if (!empty(\$phones)) \$branchInfo[] = \"Phone: \" . implode(', ', \$phones);",
        $content
    );
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Fixed: " . basename(dirname($file)) . "/FacilityBranchSeeder.php\n";
        $fixed++;
    } else {
        echo "- No changes needed: " . basename(dirname($file)) . "/FacilityBranchSeeder.php\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total files: " . count($facilityBranchSeederFiles) . "\n";
echo "Fixed: $fixed\n";


