<?php

/**
 * Script to parse Cairo CSV file and update cairo.json with all facilities
 * Usage: php parse_csv.php
 */

$csvPath = '/mnt/c/Users/nasse/Downloads/medical%20(1).xlsx - محافظة القاهرة.csv';

if (!file_exists($csvPath)) {
    // Try Windows path
    $csvPath = 'C:\\Users\\nasse\\Downloads\\medical%20(1).xlsx - محافظة القاهرة.csv';
    
    if (!file_exists($csvPath)) {
        echo "Error: CSV file not found at: $csvPath\n";
        echo "Please provide the correct path to the CSV file.\n";
        exit(1);
    }
}

echo "Parsing CSV file: $csvPath\n";

try {
    $jsonData = [
        'governorate' => [
            'ar' => 'محافظة القاهرة',
            'en' => 'Cairo Governorate'
        ],
        'medical_directory' => []
    ];
    
    $handle = fopen($csvPath, 'r');
    if (!$handle) {
        throw new Exception("Could not open CSV file");
    }
    
    $currentCategory = null;
    $currentFacility = null;
    $categories = [];
    $rowNum = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Clean row data
        $row = array_map('trim', $row);
        
        // Check if this is a category header (usually in first column)
        $firstCol = $row[0] ?? '';
        
        // Known category headers
        $categoryHeaders = [
            'المستشفيات',
            'مستشفيات متخصصة',
            'مراكز قلب متخصصة',
            'مراكز ومستشفيات العيون',
            'مراكز بصريات',
            'شركات اجهزة تعويضية',
            'عيادات تخصصية',
            'مراكز اجهزة سمعية',
            'طب طبيعى وروماتيزم',
            'علاج طبيعى وتأهيل',
            'مراكز الاشعة',
            'معامل تحاليل',
            'امراض قلب واوعية دموية',
            'جراحة اوعية دموية',
            'جراحة قلب وصدر',
            'انف واذن وحنجرة',
            'سمعيات',
            'جراحة عامة',
            'طب اطفال',
            'جراحة مسالك بولية',
            'امراض نفسية',
            'طب وجراحة العيون',
            'جلدية تناسلية',
            'نساء وتوليد',
            'طب اورام',
            'جراحة اورام',
            'امراض دم',
            'جهاز هضمى وكبد',
            'حساسية ومناعة',
            'سكر وغدد صماء',
            'طب اسنان',
            'جراحة المخ والاعصاب',
            'الامراض الصدرية',
            'جراحة عظام',
            'باطنة وكلى',
            'باطنة عامة',
            'صيدليات',
            'اوماب للبصريات و العدسات'
        ];
        
        if (in_array($firstCol, $categoryHeaders)) {
            // Save previous facility if exists
            if ($currentFacility !== null && $currentCategory !== null) {
                if (!isset($categories[$currentCategory])) {
                    $categories[$currentCategory] = [
                        'category' => [
                            'ar' => $currentCategory,
                            'en' => translateCategory($currentCategory)
                        ],
                        'facilities' => []
                    ];
                }
                $categories[$currentCategory]['facilities'][] = $currentFacility;
                $currentFacility = null;
            }
            
            $currentCategory = $firstCol;
            echo "Found category: $currentCategory (row $rowNum)\n";
            continue;
        }
        
        // Check if this is a facility name (first column has value, second might be empty or have *)
        $facilityName = $firstCol;
        $secondCol = $row[1] ?? '';
        $addressCol = $row[2] ?? '';
        $phoneCol = $row[3] ?? '';
        
        // If first column has a name and it's not a category, it's likely a facility
        if (!empty($facilityName) && !in_array($facilityName, $categoryHeaders)) {
            // Save previous facility
            if ($currentFacility !== null && $currentCategory !== null) {
                if (!isset($categories[$currentCategory])) {
                    $categories[$currentCategory] = [
                        'category' => [
                            'ar' => $currentCategory,
                            'en' => translateCategory($currentCategory)
                        ],
                        'facilities' => []
                    ];
                }
                $categories[$currentCategory]['facilities'][] = $currentFacility;
            }
            
            // Start new facility
            $currentFacility = [
                'name' => [
                    'ar' => $facilityName,
                    'en' => translateName($facilityName)
                ]
            ];
            
            if (!empty($addressCol)) {
                $currentFacility['address'] = [
                    'ar' => $addressCol,
                    'en' => $addressCol // You may want to translate this
                ];
            }
            
            $phones = [];
            if (!empty($phoneCol)) {
                $phones[] = $phoneCol;
            }
            
            // Check for additional phones in next rows
            $nextRow = fgetcsv($handle);
            if ($nextRow !== false) {
                $nextRow = array_map('trim', $nextRow);
                // If next row has phone but no facility name, it's a continuation
                if (empty($nextRow[0]) && !empty($nextRow[3])) {
                    $phones[] = $nextRow[3];
                    // Check more rows for phones
                    $pos = ftell($handle);
                    while (true) {
                        $checkRow = fgetcsv($handle);
                        if ($checkRow === false) break;
                        $checkRow = array_map('trim', $checkRow);
                        if (!empty($checkRow[0])) {
                            // Found a new facility/category, rewind
                            fseek($handle, $pos);
                            break;
                        }
                        if (!empty($checkRow[3])) {
                            $phones[] = $checkRow[3];
                            $pos = ftell($handle);
                        } else {
                            break;
                        }
                    }
                } else {
                    // Rewind if it's not a continuation
                    fseek($handle, $pos ?? -1);
                }
            }
            
            if (!empty($phones)) {
                $currentFacility['phones'] = array_unique(array_filter($phones));
            }
            
            // Check if this facility has branches (indicated by empty first column in next rows)
            // This is complex, we'll handle it in a second pass
            
        } elseif (empty($firstCol) && !empty($addressCol) && $currentFacility !== null) {
            // This might be a branch or additional address/phone
            if (!isset($currentFacility['branches'])) {
                $currentFacility['branches'] = [];
            }
            
            $branch = [];
            if (!empty($addressCol)) {
                $branch['location'] = [
                    'ar' => $addressCol,
                    'en' => $addressCol
                ];
            }
            if (!empty($phoneCol)) {
                $branch['phone'] = $phoneCol;
            }
            
            if (!empty($branch)) {
                $currentFacility['branches'][] = $branch;
            }
        } elseif (!empty($phoneCol) && $currentFacility !== null && empty($firstCol) && empty($addressCol)) {
            // Additional phone for current facility
            if (!isset($currentFacility['phones'])) {
                $currentFacility['phones'] = [];
            }
            $currentFacility['phones'][] = $phoneCol;
            $currentFacility['phones'] = array_unique(array_filter($currentFacility['phones']));
        }
    }
    
    // Don't forget the last facility
    if ($currentFacility !== null && $currentCategory !== null) {
        if (!isset($categories[$currentCategory])) {
            $categories[$currentCategory] = [
                'category' => [
                    'ar' => $currentCategory,
                    'en' => translateCategory($currentCategory)
                ],
                'facilities' => []
            ];
        }
        $categories[$currentCategory]['facilities'][] = $currentFacility;
    }
    
    fclose($handle);
    
    // Convert categories to array
    $jsonData['medical_directory'] = array_values($categories);
    
    // Write to JSON file
    $jsonPath = __DIR__ . '/cairo.json';
    $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    file_put_contents($jsonPath, $jsonContent);
    
    echo "\nSuccessfully updated: $jsonPath\n";
    echo "Total categories: " . count($categories) . "\n";
    
    $totalFacilities = 0;
    foreach ($categories as $cat) {
        $totalFacilities += count($cat['facilities']);
    }
    echo "Total facilities: $totalFacilities\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

function translateCategory($ar) {
    $translations = [
        'المستشفيات' => 'Hospitals',
        'مستشفيات متخصصة' => 'Specialized Hospitals',
        'مراكز قلب متخصصة' => 'Specialized Heart Centers',
        'مراكز ومستشفيات العيون' => 'Eye Hospitals & Centers',
        'مراكز بصريات' => 'Optics Centers',
        'شركات اجهزة تعويضية' => 'Prosthetic Devices Companies',
        'عيادات تخصصية' => 'Specialized Clinics',
        'مراكز اجهزة سمعية' => 'Hearing Aid Centers',
        'طب طبيعى وروماتيزم' => 'Physical Therapy & Rheumatology',
        'علاج طبيعى وتأهيل' => 'Physical Therapy & Rehabilitation',
        'مراكز الاشعة' => 'Radiology Centers',
        'معامل تحاليل' => 'Medical Laboratories',
        'امراض قلب واوعية دموية' => 'Cardiology & Vascular Diseases',
        'جراحة اوعية دموية' => 'Vascular Surgery',
        'جراحة قلب وصدر' => 'Cardiothoracic Surgery',
        'انف واذن وحنجرة' => 'ENT',
        'سمعيات' => 'Audiology',
        'جراحة عامة' => 'General Surgery',
        'طب اطفال' => 'Pediatrics',
        'جراحة مسالك بولية' => 'Urology',
        'امراض نفسية' => 'Psychiatry',
        'طب وجراحة العيون' => 'Ophthalmology',
        'جلدية تناسلية' => 'Dermatology & Venereology',
        'نساء وتوليد' => 'Obstetrics & Gynecology',
        'طب اورام' => 'Oncology',
        'جراحة اورام' => 'Oncological Surgery',
        'امراض دم' => 'Hematology',
        'جهاز هضمى وكبد' => 'Gastroenterology & Hepatology',
        'حساسية ومناعة' => 'Allergy & Immunology',
        'سكر وغدد صماء' => 'Diabetes & Endocrinology',
        'طب اسنان' => 'Dentistry',
        'جراحة المخ والاعصاب' => 'Neurosurgery',
        'الامراض الصدرية' => 'Pulmonology',
        'جراحة عظام' => 'Orthopedic Surgery',
        'باطنة وكلى' => 'Internal Medicine & Nephrology',
        'باطنة عامة' => 'General Internal Medicine',
        'صيدليات' => 'Pharmacies',
        'اوماب للبصريات و العدسات' => 'Omap Optics'
    ];
    
    return $translations[$ar] ?? $ar;
}

function translateName($ar) {
    // Simple translation - you may want to improve this
    // For now, just return the Arabic name as English
    return $ar;
}


