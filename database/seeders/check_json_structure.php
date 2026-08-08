<?php

/**
 * Check all JSON files to ensure:
 * 1. Facilities do NOT have address or phones at facility level
 * 2. Every facility has at least one branch
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

$issues = [];
$fixed = [];
$totalFiles = 0;
$totalFacilities = 0;
$issuesCount = 0;

echo "=== JSON Structure Validation Report ===\n\n";

foreach ($jsonFiles as $jsonFile) {
    $totalFiles++;
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
    
    echo "\n📄 Checking: $relativePath\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($data['medical_directory'] as $categoryIndex => $category) {
        if (!isset($category['facilities'])) {
            continue;
        }
        
        foreach ($category['facilities'] as $facilityIndex => $facility) {
            $totalFacilities++;
            $facilityName = $facility['name']['ar'] ?? $facility['name']['en'] ?? 'Unknown';
            $hasIssues = false;
            $facilityIssues = [];
            
            // Check if facility has address or phones at facility level
            if (isset($facility['address'])) {
                $hasIssues = true;
                $facilityIssues[] = "Has 'address' at facility level (should be in branch)";
            }
            
            if (isset($facility['phones'])) {
                $hasIssues = true;
                $facilityIssues[] = "Has 'phones' at facility level (should be in branch)";
            }
            
            if (isset($facility['phone'])) {
                $hasIssues = true;
                $facilityIssues[] = "Has 'phone' at facility level (should be in branch)";
            }
            
            if (isset($facility['hotline'])) {
                // Hotline might be acceptable, but let's flag it for review
                $facilityIssues[] = "Has 'hotline' at facility level (consider moving to branch)";
            }
            
            // Check if facility has branches
            if (!isset($facility['branches']) || empty($facility['branches'])) {
                $hasIssues = true;
                $facilityIssues[] = "Missing branches array or branches is empty";
            } else {
                // Validate branches structure
                foreach ($facility['branches'] as $branchIndex => $branch) {
                    // Check if branch is just a string (invalid)
                    if (is_string($branch)) {
                        $hasIssues = true;
                        $facilityIssues[] = "Branch #" . ($branchIndex + 1) . " is a string instead of object";
                    }
                }
            }
            
            if ($hasIssues) {
                $issuesCount++;
                $issues[$relativePath][] = [
                    'facility' => $facilityName,
                    'category' => $category['category']['ar'] ?? $category['category']['en'] ?? 'Unknown',
                    'index' => $facilityIndex,
                    'categoryIndex' => $categoryIndex,
                    'issues' => $facilityIssues,
                    'current_structure' => $facility
                ];
                
                echo "  ❌ Facility: $facilityName\n";
                foreach ($facilityIssues as $issue) {
                    echo "     - $issue\n";
                }
            }
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total files checked: $totalFiles\n";
echo "Total facilities checked: $totalFacilities\n";
echo "Facilities with issues: $issuesCount\n";
echo "Files with issues: " . count($issues) . "\n\n";

if (!empty($issues)) {
    echo "DETAILED ISSUES BY FILE:\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($issues as $file => $fileIssues) {
        echo "File: $file\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($fileIssues as $issue) {
            echo "  Facility: {$issue['facility']}\n";
            echo "  Category: {$issue['category']}\n";
            echo "  Issues:\n";
            foreach ($issue['issues'] as $issueDesc) {
                echo "    - $issueDesc\n";
            }
            echo "\n";
        }
        echo "\n";
    }
    
    // Save detailed report
    file_put_contents($baseDir . '/json_structure_issues.json', json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Detailed issues saved to: json_structure_issues.json\n";
} else {
    echo "✅ All JSON files are properly structured!\n";
    echo "✅ All facilities have branches\n";
    echo "✅ No address/phones at facility level\n";
}

