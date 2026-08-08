<?php

/**
 * Script to fix remaining FacilitySeeder files
 * This script fixes the pattern: removes 'address' and 'phone' from Facility::updateOrCreate
 * and updates branch creation to use phone array
 */

$filesToFix = [
    'MarsaMatrouhData/FacilitySeeder.php',
    'MenofiaData/FacilitySeeder.php',
    'KafrElSheikhData/FacilitySeeder.php',
    'PortSaidData/FacilitySeeder.php',
    'NorthSinaiData/FacilitySeeder.php',
    'BeniSuefData/FacilitySeeder.php',
    'NewValleyData/FacilitySeeder.php',
    'SouthSinaiData/FacilitySeeder.php',
    'CairoGizaData/FacilitySeeder.php',
    'SharqiaData/FacilitySeeder.php',
    'GizaData/FacilitySeeder.php',
    'DakahliaData/FacilitySeeder.php',
    'SuezData/FacilitySeeder.php',
    'RedSeaData/FacilitySeeder.php',
    'IsmailiaData/FacilitySeeder.php',
    'SuezGharbiaData/FacilitySeeder.php',
    'QalyubiaData/FacilitySeeder.php',
];

$seedersDir = __DIR__;
$fixed = 0;

foreach ($filesToFix as $relativePath) {
    $file = $seedersDir . '/' . $relativePath;
    
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Pattern 1: Remove 'address' and 'phone' from Facility::updateOrCreate
    $content = preg_replace(
        "/\s*'address'\s*=>\s*\[[^\]]*\],?\s*/",
        "",
        $content
    );
    
    $content = preg_replace(
        "/\s*'phone'\s*=>\s*\$firstPhone,?\s*/",
        "",
        $content
    );
    
    // Pattern 2: Update comment
    $content = str_replace(
        "// Create or update facility",
        "// Create or update facility (without address and phone - those go in branches)",
        $content
    );
    
    // Pattern 3: Fix branch creation - change $firstPhone to $phones and add phone array conversion
    $content = preg_replace(
        "/if \(\$existingBranchesCount === 0 && \(!empty\(\$addressAr\) \|\| !empty\(\$firstPhone\)\)\) \{/",
        "if (\$existingBranchesCount === 0 && (!empty(\$addressAr) || !empty(\$phones))) {",
        $content
    );
    
    // Add phone array conversion before FacilityBranch::updateOrCreate
    if (strpos($content, "// Convert phones to array format") === false) {
        $content = preg_replace(
            "/(\$branchSlug = Str::slug\(\$facility->slug \. '-main'\);)/",
            "$1\n                            \n                            // Convert phones to array format\n                            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;",
            $content
        );
    }
    
    // Replace 'phone' => $firstPhone with 'phone' => $phoneArray
    $content = str_replace("'phone' => \$firstPhone,", "'phone' => \$phoneArray,", $content);
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Fixed: $relativePath\n";
        $fixed++;
    } else {
        echo "- No changes needed: $relativePath\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total files: " . count($filesToFix) . "\n";
echo "Fixed: $fixed\n";


