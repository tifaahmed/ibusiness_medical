<?php

/**
 * Script to parse HTML file and update alexandria.json with all facilities
 * Usage: php update_from_html.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Database\Seeders\AlexandriaData\AlexandriaDataParser;

// Path to HTML file - adjust as needed
$htmlPath = '/mnt/c/Users/nasse/Downloads/الاسكندرية.html';

if (!file_exists($htmlPath)) {
    // Try Windows path
    $htmlPath = 'C:\\Users\\nasse\\Downloads\\الاسكندرية.html';
    
    if (!file_exists($htmlPath)) {
        echo "Error: HTML file not found at: $htmlPath\n";
        echo "Please provide the correct path to the HTML file.\n";
        exit(1);
    }
}

echo "Parsing HTML file: $htmlPath\n";

try {
    $parser = new AlexandriaDataParser($htmlPath);
    $facilities = $parser->parseFacilities();
    
    echo "Found " . count($facilities) . " facilities\n";
    
    // Now convert to JSON structure
    $jsonData = [
        'governorate' => [
            'ar' => 'الاسكندرية',
            'en' => 'Alexandria'
        ],
        'medical_directory' => []
    ];
    
    // Group facilities by category
    $categories = [];
    
    foreach ($facilities as $facility) {
        $categoryAr = $facility['category_ar'] ?? 'غير محدد';
        $categoryEn = $facility['category_en'] ?? 'Unspecified';
        
        if (!isset($categories[$categoryAr])) {
            $categories[$categoryAr] = [
                'category' => [
                    'ar' => $categoryAr,
                    'en' => $categoryEn
                ],
                'facilities' => []
            ];
        }
        
        $facilityData = [
            'name' => [
                'ar' => $facility['name_ar'] ?? '',
                'en' => $facility['name_en'] ?? ''
            ]
        ];
        
        if (!empty($facility['address_ar']) || !empty($facility['address_en'])) {
            $facilityData['address'] = [
                'ar' => $facility['address_ar'] ?? '',
                'en' => $facility['address_en'] ?? ''
            ];
        }
        
        if (!empty($facility['phones'])) {
            $facilityData['phones'] = is_array($facility['phones']) 
                ? $facility['phones'] 
                : [$facility['phones']];
        }
        
        if (!empty($facility['hotline'])) {
            $facilityData['hotline'] = $facility['hotline'];
        }
        
        if (!empty($facility['branches'])) {
            $facilityData['branches'] = [];
            foreach ($facility['branches'] as $branch) {
                if (is_string($branch)) {
                    $facilityData['branches'][] = $branch;
                } else {
                    $branchData = [];
                    if (isset($branch['location'])) {
                        if (is_array($branch['location'])) {
                            $branchData['location'] = $branch['location'];
                        } else {
                            $branchData['location'] = $branch['location'];
                        }
                    } elseif (isset($branch['address_ar']) || isset($branch['address_en'])) {
                        $branchData['location'] = [
                            'ar' => $branch['address_ar'] ?? '',
                            'en' => $branch['address_en'] ?? ''
                        ];
                    }
                    
                    if (isset($branch['phone'])) {
                        $branchData['phone'] = $branch['phone'];
                    }
                    if (isset($branch['phones'])) {
                        $branchData['phones'] = is_array($branch['phones']) 
                            ? $branch['phones'] 
                            : [$branch['phones']];
                    }
                    
                    if (!empty($branchData)) {
                        $facilityData['branches'][] = $branchData;
                    }
                }
            }
        }
        
        if (!empty($facility['specialty_ar']) || !empty($facility['specialty_en'])) {
            $facilityData['specialty'] = [
                'ar' => $facility['specialty_ar'] ?? '',
                'en' => $facility['specialty_en'] ?? ''
            ];
        }
        
        $categories[$categoryAr]['facilities'][] = $facilityData;
    }
    
    $jsonData['medical_directory'] = array_values($categories);
    
    // Write to JSON file
    $jsonPath = __DIR__ . '/alexandria.json';
    $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    file_put_contents($jsonPath, $jsonContent);
    
    echo "Successfully updated: $jsonPath\n";
    echo "Total categories: " . count($categories) . "\n";
    echo "Total facilities: " . count($facilities) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}


