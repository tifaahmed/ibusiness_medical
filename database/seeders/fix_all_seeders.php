<?php

/**
 * Script to fix all FacilitySeeder files to remove address and phone from facilities
 */

$seedersDir = __DIR__;
$facilitySeederFiles = glob($seedersDir . '/*/FacilitySeeder.php');
$facilityBranchSeederFiles = glob($seedersDir . '/*/FacilityBranchSeeder.php');

if (empty($facilitySeederFiles)) {
    echo "No FacilitySeeder files found\n";
    exit(1);
}

echo "Found " . count($facilitySeederFiles) . " FacilitySeeder files\n";
echo "Fixing files...\n\n";

$fixed = 0;

// Fix FacilitySeeder files
foreach ($facilitySeederFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Remove 'address' => [...] from Facility::updateOrCreate
    $content = preg_replace(
        "/\s*'address'\s*=>\s*\[[^\]]*\],?\s*/",
        "",
        $content
    );
    
    // Remove 'phone' => $variable from Facility::updateOrCreate
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*\$[^,)]+,?\s*/",
        "",
        $content
    );
    
    // Remove 'phone' => null,
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*null,?\s*/",
        "",
        $content
    );
    
    // Update comment before Facility::updateOrCreate
    $content = str_replace(
        "// Create or update facility",
        "// Create or update facility (without address and phone - those go in branches)",
        $content
    );
    
    // Fix phone array conversion in branch creation
    // Replace: 'phone' => $firstPhone, with phone array
    if (strpos($content, "'phone' => \$firstPhone") !== false) {
        // Find the context - if it's in FacilityBranch::updateOrCreate, convert to array
        $content = preg_replace(
            "/'phone'\s*=>\s*\$firstPhone/",
            "'phone' => \$phoneArray",
            $content
        );
        
        // Add phone array conversion before the branch creation
        if (strpos($content, "// Convert phones to array format") === false) {
            $content = preg_replace(
                "/(\$branchSlug = Str::slug\(\$facility->slug \. '-main'\);)/",
                "$1\n                            \n                            // Convert phones to array format\n                            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;",
                $content
            );
        }
    }
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Fixed: $file\n";
        $fixed++;
    } else {
        echo "- No changes needed: $file\n";
    }
}

// Fix FacilityBranchSeeder files
echo "\nFound " . count($facilityBranchSeederFiles) . " FacilityBranchSeeder files\n";
echo "Fixing files...\n\n";

foreach ($facilityBranchSeederFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Replace: Get address and phone from facility -> Get from facilityData
    $pattern = "/\/\/ Get address and phone from facility\s*\$facilityAddress = \$facility->address;.*?\$branchPhone = [^;]+;/s";
    $replacement = "// Get address and phone from facilityData (JSON), not from facility model\n            \$branchAddressAr = (string) (\$facilityData['address']['ar'] ?? '');\n            \$branchAddressEn = (string) (\$facilityData['address']['en'] ?? \$branchAddressAr);\n            \n            // Handle phones - can be in 'phones' array or 'hotline' field\n            \$phones = \$facilityData['phones'] ?? [];\n            if (empty(\$phones) && !empty(\$facilityData['hotline'])) {\n                \$phones = [\$facilityData['hotline']];\n            }\n            \n            // Convert phones to array format\n            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;";
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Replace: $branchPhone with $phoneArray
    $content = str_replace("'phone' => \$branchPhone", "'phone' => \$phoneArray", $content);
    
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
echo "Total files processed: " . (count($facilitySeederFiles) + count($facilityBranchSeederFiles)) . "\n";
echo "Fixed: $fixed\n";


