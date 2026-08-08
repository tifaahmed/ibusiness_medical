<?php

/**
 * Script to check for missing data from HTML files compared to JSON files
 */

require __DIR__ . '/../../vendor/autoload.php';

function parseHtmlTable($htmlContent) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
    $xpath = new DOMXPath($dom);
    
    $rows = $xpath->query('//table[@class="waffle"]//tr');
    $data = [];
    $currentCategory = null;
    $currentFacility = null;
    
    foreach ($rows as $row) {
        $cells = $xpath->query('.//td', $row);
        if ($cells->length < 2) continue;
        
        $cellTexts = [];
        foreach ($cells as $cell) {
            $text = trim($cell->textContent);
            if (!empty($text)) {
                $cellTexts[] = $text;
            }
        }
        
        if (empty($cellTexts)) continue;
        
        // Check if it's a category header (yellow background or contains category keywords)
        $firstCell = $cells->item(0);
        $bgColor = $firstCell->getAttribute('style');
        $isCategory = strpos($bgColor, '#ffff00') !== false || 
                      strpos($bgColor, 'background-color:#ffff00') !== false ||
                      preg_match('/المستشفيات|معامل|مراكز|صيدليات|الاطباء|علاج طبيعي|طب اسنان|عظام|باطنة|قلب|عيون/i', $cellTexts[0]);
        
        if ($isCategory && count($cellTexts) >= 1) {
            $currentCategory = $cellTexts[0];
            $currentFacility = null;
            continue;
        }
        
        if ($currentCategory) {
            // Try to extract facility data
            if (count($cellTexts) >= 2) {
                $name = $cellTexts[0];
                $address = isset($cellTexts[1]) ? $cellTexts[1] : '';
                $phone = isset($cellTexts[2]) ? $cellTexts[2] : (isset($cellTexts[1]) && preg_match('/\d+/', $cellTexts[1]) ? $cellTexts[1] : '');
                
                if (!empty($name) && !preg_match('/^[A-Z]$/', $name)) {
                    if (!isset($data[$currentCategory])) {
                        $data[$currentCategory] = [];
                    }
                    
                    $facilityKey = $name;
                    if (!isset($data[$currentCategory][$facilityKey])) {
                        $data[$currentCategory][$facilityKey] = [
                            'name' => $name,
                            'address' => $address,
                            'phones' => []
                        ];
                    }
                    
                    if (!empty($phone) && preg_match('/\d+/', $phone)) {
                        $data[$currentCategory][$facilityKey]['phones'][] = $phone;
                    }
                }
            }
        }
    }
    
    return $data;
}

function loadJsonFile($path) {
    if (!file_exists($path)) {
        return null;
    }
    $content = file_get_contents($path);
    return json_decode($content, true);
}

function normalizeName($name) {
    // Remove extra spaces and normalize
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

function compareData($htmlData, $jsonData, $governorateName) {
    $missing = [];
    $htmlCount = 0;
    $jsonCount = 0;
    
    foreach ($htmlData as $category => $facilities) {
        $htmlCount += count($facilities);
        
        // Find matching category in JSON
        $jsonCategory = null;
        foreach ($jsonData['medical_directory'] ?? [] as $cat) {
            if (isset($cat['category']['ar']) && 
                (stripos($cat['category']['ar'], $category) !== false || 
                 stripos($category, $cat['category']['ar']) !== false)) {
                $jsonCategory = $cat;
                break;
            }
        }
        
        if (!$jsonCategory) {
            $missing[] = "Category missing: $category";
            continue;
        }
        
        $jsonCount += count($jsonCategory['facilities'] ?? []);
        
        // Check each facility
        foreach ($facilities as $facility) {
            $found = false;
            $normalizedHtmlName = normalizeName($facility['name']);
            
            foreach ($jsonCategory['facilities'] ?? [] as $jsonFacility) {
                $normalizedJsonName = normalizeName($jsonFacility['name']['ar'] ?? '');
                
                if (stripos($normalizedJsonName, $normalizedHtmlName) !== false ||
                    stripos($normalizedHtmlName, $normalizedJsonName) !== false) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $missing[] = [
                    'category' => $category,
                    'facility' => $facility['name'],
                    'address' => $facility['address'] ?? '',
                    'phones' => $facility['phones'] ?? []
                ];
            }
        }
    }
    
    return [
        'missing' => $missing,
        'html_count' => $htmlCount,
        'json_count' => $jsonCount
    ];
}

// Check each governorate
$governorates = [
    'كفر الشيخ' => [
        'html' => '/mnt/c/Users/nasse/Downloads/كفر الشيخ.html',
        'json' => __DIR__ . '/KafrElSheikhData/kafr-el-sheikh.json'
    ],
    'مرسى مطروح' => [
        'html' => '/mnt/c/Users/nasse/Downloads/مرسى مطروح.html',
        'json' => __DIR__ . '/MarsaMatrouhData/marsa-matrouh.json'
    ],
    'دمياط' => [
        'html' => '/mnt/c/Users/nasse/Downloads/دمياط.html',
        'json' => __DIR__ . '/DamiettaData/damietta.json'
    ],
    'سوهاج' => [
        'html' => '/mnt/c/Users/nasse/Downloads/سوهاج.html',
        'json' => __DIR__ . '/SohagData/sohag.json'
    ],
    'قنا' => [
        'html' => '/mnt/c/Users/nasse/Downloads/قنا.html',
        'json' => __DIR__ . '/QenaData/qena.json'
    ],
    'شمال سيناء' => [
        'html' => '/mnt/c/Users/nasse/Downloads/شمال سيناء.html',
        'json' => __DIR__ . '/NorthSinaiData/north-sinai.json'
    ]
];

$results = [];

foreach ($governorates as $name => $paths) {
    echo "\n=== Checking $name ===\n";
    
    // Try both paths
    $htmlPath = $paths['html'];
    if (!file_exists($htmlPath)) {
        $htmlPath = str_replace('/mnt/c/', 'C:\\', $htmlPath);
    }
    
    if (!file_exists($htmlPath)) {
        echo "HTML file not found: {$paths['html']}\n";
        continue;
    }
    
    $htmlContent = file_get_contents($htmlPath);
    $htmlData = parseHtmlTable($htmlContent);
    
    $jsonData = loadJsonFile($paths['json']);
    if (!$jsonData) {
        echo "JSON file not found: {$paths['json']}\n";
        continue;
    }
    
    $comparison = compareData($htmlData, $jsonData, $name);
    
    echo "HTML facilities: {$comparison['html_count']}\n";
    echo "JSON facilities: {$comparison['json_count']}\n";
    echo "Missing: " . count($comparison['missing']) . "\n";
    
    if (!empty($comparison['missing'])) {
        foreach ($comparison['missing'] as $item) {
            if (is_array($item)) {
                echo "  - {$item['facility']} ({$item['category']})\n";
            } else {
                echo "  - $item\n";
            }
        }
    }
    
    $results[$name] = $comparison;
}

// Save report
$report = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/missing_data_report.json', $report);
echo "\nReport saved to missing_data_report.json\n";


