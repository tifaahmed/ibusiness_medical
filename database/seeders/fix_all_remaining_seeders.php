<?php

/**
 * Script to fix ALL remaining FacilitySeeder files
 * Removes 'address' and 'phone' from Facility::updateOrCreate
 * Updates branch creation to use phone array
 */

$filesToFix = [
    'PortSaidData/FacilitySeeder.php' => '$this->portSaid',
    'NorthSinaiData/FacilitySeeder.php' => '$this->northSinai',
    'NewValleyData/FacilitySeeder.php' => '$this->newValley',
    'SouthSinaiData/FacilitySeeder.php' => '$this->southSinai',
    'CairoGizaData/FacilitySeeder.php' => '$governorate',
    'SharqiaData/FacilitySeeder.php' => '$this->sharqia',
    'GizaData/FacilitySeeder.php' => '$this->giza',
    'DakahliaData/FacilitySeeder.php' => '$this->dakahlia',
    'SuezData/FacilitySeeder.php' => '$this->suez',
    'RedSeaData/FacilitySeeder.php' => '$this->redSea',
    'IsmailiaData/FacilitySeeder.php' => '$this->ismailia',
    'SuezGharbiaData/FacilitySeeder.php' => '$this->suezGharbia',
    'QalyubiaData/FacilitySeeder.php' => '$this->qalyubia',
];

$seedersDir = __DIR__;
$fixed = 0;

foreach ($filesToFix as $relativePath => $governorateVar) {
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
    
    // Pattern 3: Fix branch creation condition
    $content = preg_replace(
        "/if \(\$existingBranchesCount === 0 && \(!empty\(\$addressAr\) \|\| !empty\(\$firstPhone\)\)\) \{/",
        "if (\$existingBranchesCount === 0 && (!empty(\$addressAr) || !empty(\$phones))) {",
        $content
    );
    
    // Pattern 4: Add phone array conversion before FacilityBranch::updateOrCreate
    if (strpos($content, "// Convert phones to array format") === false) {
        $content = preg_replace(
            "/(\$branchSlug = Str::slug\(\$facility->slug \. '-main'\);)/",
            "$1\n                            \n                            // Convert phones to array format\n                            \$phoneArray = !empty(\$phones) ? array_map('trim', array_filter(\$phones, fn(\$p) => !empty(\$p))) : null;",
            $content
        );
    }
    
    // Pattern 5: Replace 'phone' => $firstPhone with 'phone' => $phoneArray
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


