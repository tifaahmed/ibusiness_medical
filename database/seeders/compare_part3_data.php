<?php

// Provided data from user
$providedData = [
    'Cairo' => [
        'Hospitals' => [
            'دار الحكمة' => ['name' => 'Dar El Hekma Hospital', 'address' => '10ش محمد حسين هيكل من عباس العقاد م.نصر', 'phones' => ['26739050', '26739051']],
            'السلام الدولى' => ['name' => 'Al Salam International Hospital', 'address' => 'كورنيش النيل – المعادى', 'phones' => ['25240250', '25240077']],
            'النيل بدراوى' => ['name' => 'Nile Badrawy Hospital', 'address' => 'كورنيش النيل – طريق المعادى', 'phones' => ['25242446', '25240022']],
            'قصر العينى التعليمى' => ['name' => 'Kasr El Aini Teaching Hospital', 'address' => 'شارع القصر العينى', 'phones' => ['25313820', '25313821']],
            'الجنزورى' => ['name' => 'El Ganzouri Hospital', 'address' => '63 ش طومان باى - سراى القبة', 'phones' => ['22585607', '22585663']],
            'معهد ناصر' => ['name' => 'Nasser Institute Hospital', 'address' => '1351 كورنيش النيل – شبر المظلات', 'phones' => ['24309224', '22038277']],
            'المجمع الطبى العسكرى بكوبرى القبه' => ['name' => 'Military Medical Complex - Kobri El Qoba', 'address' => 'كوبرى القبه – القاهره', 'phones' => ['24017180', '24017187']],
            'القوات المسلحه بالمعادى' => ['name' => 'Armed Forces Hospital - Maadi', 'address' => 'كورنيش النيل بالمعادى', 'phones' => ['25256350', '25256345']],
            'الايطالى' => ['name' => 'Italian Hospital', 'address' => '17ش السرايات بالعباسية', 'phones' => ['26740088']],
            'سان بيتر الدولى' => ['name' => 'San Peter International Hospital', 'address' => '3 ش عبد الرحمن الرافعى مصر الجديده', 'phones' => ['26235797', '26241483']],
            'المقاولون العرب' => ['name' => 'Arab Contractors Hospital (19660)', 'address' => '465 الجبل الاخضر - مدينة نصر', 'phones' => ['24881475', '23426000']],
            'غمره العسكرى' => ['name' => 'Ghamra Military Hospital', 'address' => 'كوبرى باغوص - غمره', 'phones' => ['22354288', '22353451']],
            'فلسطين' => ['name' => 'Palestine Hospital', 'address' => '64ش الثورة – مصر الجديدة', 'phones' => ['22909007', '22909008']],
            'الهلال برمسيس' => ['name' => 'Crescent Hospital - Ramses', 'address' => '34ش رمسيس', 'phones' => ['25750171', '25750169']],
            'الزيتون التخصصى' => ['name' => 'Zeitoun Specialized Hospital', 'address' => 'شارع عمر المختار – الاميرية', 'phones' => ['22846000', '22847000']],
            'شجرة الدر' => ['name' => 'Shagaret El Dorr Hospital', 'address' => '6 ش فؤاد الاهوانى – الزمالك', 'phones' => ['27361386', '27366251']],
            'الصفوة بشبرا' => ['name' => 'El Safwa Hospital - Shubra', 'address' => '3ش منية السيرج – شارع شبرا', 'phones' => ['24302001', '24304005']],
        ]
    ],
    'Giza' => [
        'Hospitals' => [
            'الهرم' => ['name' => 'Haram Hospital', 'address' => '104ش الهرم – الكوم الاخضر', 'phones' => ['33860235']],
            'الجيزة الدولى' => ['name' => 'Giza International Hospital', 'address' => '334ش الهرم – المساحة', 'phones' => ['37810017']],
            'الامل بالمهندسين' => ['name' => 'Al Amal Hospital - Mohandessin', 'address' => '25ش فوزى رماح من شهاب المهندسين', 'phones' => ['33479217']],
            'الشبراويشى' => ['name' => 'El Shabraweishy Hospital', 'address' => 'ميدان السد العالى – فينى الدقى', 'phones' => ['37606444']],
            'مصر الدولى' => ['name' => 'Misr International Hospital', 'address' => '12ش السرايا – ميدان فينى – الدقى', 'phones' => ['37608263']],
            'بدراوى' => ['name' => 'Badrawy Hospital', 'address' => '39 ش دمشق المهندسين', 'phones' => ['37490018']],
            'الجزيرة' => ['name' => 'El Gezira Hospital', 'address' => '8ش احمد لطفى السيد - فيصل', 'phones' => ['33925413']],
            'الشيخ زايد التخصصى' => ['name' => 'Sheikh Zayed Specialized Hospital', 'address' => 'المحور المركزى – مدينة الشيخ زايد', 'phones' => ['38500921']],
            'الصفوة (16361)' => ['name' => 'El Safwa Hospital (16361)', 'address' => '6اكتوبر الحى الاول بجوار مسجد الحصرى', 'phones' => ['38372255']],
            'الوادى' => ['name' => 'El Wadi Hospital', 'address' => 'جامع الحصرى – 6 اكتوبر', 'phones' => ['38371200']],
            'دريم' => ['name' => 'Dream Hospital', 'address' => 'طريق الواحات - دريم لاند - 6 اكتوبر', 'phones' => ['38580436']],
            'جلوبال كير' => ['name' => 'Global Care Hospital', 'address' => 'المحور المركزي الشيخ زايد', 'phones' => ['38512920']],
        ]
    ],
    'Qalyubia' => [
        'Hospitals' => [
            'الامل بشبرا الخيمة' => ['name' => 'Al Amal Hospital - Shubra El Kheima', 'address' => '15ش 25 البنزينة - عزبة عثمان – شبرا الخيمة', 'phones' => ['46043460', '46043465']],
            'الفيومى' => ['name' => 'El Fayoumy Hospital', 'address' => 'بنها - بجوار نقابة التطبيقيين', 'phones' => ['2460177']],
            'الراعى الصالح' => ['name' => 'Good Shepherd Hospital', 'address' => 'ش.سعد زغلول بجوار بنك مصر - بنها', 'phones' => ['3255258', '3260280']],
            'صلاح الدين' => ['name' => 'Salah El Din Hospital', 'address' => 'ش صلاح الدين - الخانكه', 'phones' => ['44699656']],
            'الصفا' => ['name' => 'El Safa Hospital', 'address' => 'امام مدرسة الشبان المسلمين - بنها', 'phones' => ['3233410']],
            'تبارك للأطفال' => ['name' => 'Tabarak Children Hospital', 'phones' => ['44084080']],
        ]
    ],
    'Menoufia' => [
        'Hospitals' => [
            'المعلمين الجديد' => ['name' => 'New Teachers Hospital', 'address' => 'شبين الكوم - البر الشرقى', 'phones' => ['482194518']],
            'المواساة الاسلامى' => ['name' => 'Al Mawasah Islamic Hospital', 'address' => 'شبين الكوم – نهاية الكوبرى العلوى', 'phones' => ['2321718']],
            'السادات التخصصي' => ['name' => 'Sadat Specialized Hospital', 'address' => 'محور خدمات الحي الاول مدينة السادات', 'phones' => ['2601055']],
            'الرواد التخصصى' => ['name' => 'Pioneers Specialized Hospital', 'address' => 'ش سعد زغلول - منوف', 'phones' => ['3677774', '3677775']],
            'عرفة التخصصى' => ['name' => 'Arafa Specialized Hospital', 'address' => 'شبين الكوم - ش الرحمة', 'phones' => ['2224452', '2325951']],
        ]
    ],
    'North Sinai' => [
        'Hospitals' => [
            'العريش العسكرى' => ['name' => 'Arish Military Hospital', 'address' => 'العريش - ضاحية السلام بجوار مديرية الامن', 'phones' => ['3352018', '3324019']],
            'سيناء التخصصى' => ['name' => 'Sinai Specialized Hospital', 'address' => '33ش بورسعيد خلف البنك الاهلى - العريش', 'phones' => ['0123702571']],
        ],
        'Laboratories' => [
            'د. سمير شاكر' => ['name' => 'Dr. Samir Shaker Lab', 'phones' => ['01018018986', '3366339']],
            'سينا لاب' => ['name' => 'Sina Lab', 'phones' => ['3354045', '3504045', '101639534']],
        ],
        'Pharmacies' => [
            'احمد و على' => ['name' => 'Ahmed & Ali Pharmacy', 'phones' => ['3367788']],
            'دراهم' => ['name' => 'Darahem Pharmacy', 'phones' => ['3341909']],
            'د.محمد الغالى' => ['name' => 'Dr. Mohamed El Ghaly Pharmacy', 'phones' => ['3354945']],
            'الشوربجى' => ['name' => 'El Shorbagi Pharmacy', 'phones' => ['3362144']],
        ]
    ],
    'South Sinai' => [
        'Hospitals' => [
            'شرم الشيخ الدولى' => ['name' => 'Sharm El Sheikh International Hospital', 'address' => 'شرم الشيخ – طريق السلام – حى النور', 'phones' => ['3661624', '3660893', '3660894']],
            'جبل سيناء' => ['name' => 'Mount Sinai Hospital', 'address' => 'طريق السلام – حى النور - شرم الشيخ', 'phones' => ['3661744', '3661745']],
            'جنوب سيناء' => ['name' => 'South Sinai Hospital', 'address' => '10 ش راس كيندي طريق السلام شرم الشيخ', 'phones' => ['3666020', '3666030']],
            'مبارك العسكرى بالطور' => ['name' => 'Mubarak Military Hospital - El Tor', 'address' => 'مدينة الطور', 'phones' => ['3771757', '3771758']],
        ],
        'Laboratories' => [
            'الفيروز للتحاليل الطبية' => ['name' => 'Al Fayrouz Medical Lab', 'phones' => ['3776690', '01062889088']],
        ],
        'Pharmacies' => [
            'العزبى' => ['name' => 'El Ezaby Pharmacies', 'phones' => ['3665256', '9205486', '3600312']],
            'الشناوى الجديدة' => ['name' => 'New El Shenawy Pharmacy', 'phones' => ['3777007']],
        ]
    ],
    'Marsa Matrouh' => [
        'Hospitals' => [
            'مطروح العسكرى' => ['name' => 'Matrouh Military Hospital', 'address' => 'بجوار مبنى المحافظة - مطروح', 'phones' => ['4939344', '4935286']],
            'عبد الله عيسى التخصصى' => ['name' => 'Abdullah Eissa Specialized Hospital', 'address' => 'مطروح - 18ش عبدلله عيسى متفرع من ش اسكندرية', 'phones' => ['4934823', '4943474']],
        ],
        'Laboratories' => [
            'د.مؤمنة كامل (المختبر)' => ['name' => 'Dr. Moumena Kamel Lab', 'phones' => ['4945661']],
            'الفا' => ['name' => 'Alfa Labs', 'phones' => ['16191']],
            'البرج' => ['name' => 'Al Borg Lab', 'phones' => ['4944337']],
        ],
        'Radiology Centers' => [
            'تكنو سكان اسامة خليل' => ['name' => 'Techno Scan Osama Khalil', 'phones' => ['01027776518']],
        ],
        'Pharmacies' => [
            'نوح' => ['name' => 'Nouh Pharmacy', 'phones' => ['4934300']],
            'الحلوانى' => ['name' => 'El Helwany Pharmacy', 'phones' => ['4934558']],
            'د/ فضل مطير' => ['name' => 'Dr. Fadl Matir Pharmacy', 'phones' => ['1008641051']],
        ]
    ],
    'New Valley' => [
        'Hospitals' => [
            'السلام - د. احمد صالح' => ['name' => 'Al Salam Hospital - Dr. Ahmed Saleh', 'address' => 'ش جمال عبد الناصر الخارجة', 'phones' => ['7933377', '7933399']],
            'هند التخصصى' => ['name' => 'Hend Specialized Hospital', 'address' => 'ش العاشر من رمضان موط - الداخلة', 'phones' => ['7820161', '7820169']],
        ],
        'Laboratories' => [
            'المختبر' => ['name' => 'Al Mokhtar Lab', 'phones' => ['19014']],
            'البرج' => ['name' => 'Al Borg Lab', 'phones' => ['19911']],
        ],
        'Pharmacies' => [
            'محمد سعد' => ['name' => 'Mohamed Saad Pharmacy', 'phones' => ['1005675237']],
            'ايمان محمد حسين' => ['name' => 'Iman Mohamed Hussein Pharmacy', 'phones' => ['1211778857']],
        ]
    ],
    'Red Sea' => [
        'Hospitals' => [
            'السلام الغردقة' => ['name' => 'Al Salam Hospital Hurghada', 'address' => 'طريق الكورنيش بجوار قرية عربية - الغردقة', 'phones' => ['3548785', '3548786', '3548787']],
            'البحر الاحمر' => ['name' => 'Red Sea Hospital', 'address' => 'زويدان من ش النصر - الدهار - الغردقه', 'phones' => ['3543850']],
            'الحكمة للخدمات الطبية' => ['name' => 'Al Hekma Medical Services Hospital', 'address' => 'ش النصر الرئيسى - الدهار - الغردقة', 'phones' => ['3553999', '3554888']],
            'المصرى بالغردقة' => ['name' => 'Egyptian Hospital Hurghada', 'phones' => ['3450318']],
        ],
        'Laboratories' => [
            'الفا' => ['name' => 'Alfa Labs', 'phones' => ['16191']],
            'النخبة' => ['name' => 'Elite Labs', 'phones' => ['1220444432']],
            'د.مؤمنة كامل (المختبر)' => ['name' => 'Dr. Moumena Kamel Lab', 'phones' => ['3541187']],
            'البركه للتحاليل الطبيه' => ['name' => 'Al Baraka Medical Labs', 'phones' => ['15034']],
        ],
        'Pharmacies' => [
            'العزبى' => ['name' => 'El Ezaby Pharmacies', 'phones' => ['3464866', '3462687']],
            'عبير' => ['name' => 'Abeer Pharmacy', 'phones' => ['3553395']],
        ]
    ]
];

// Load existing JSON files
$existingFiles = [
    'Cairo' => 'CairoData/cairo.json',
    'Giza' => 'GizaData/giza.json',
    'Qalyubia' => 'QalyubiaData/qalyubia.json',
    'Menoufia' => 'MenofiaData/menofia.json',
    'North Sinai' => 'NorthSinaiData/north-sinai.json',
    'South Sinai' => 'SouthSinaiData/south-sinai.json',
    'Marsa Matrouh' => 'MarsaMatrouhData/marsa-matrouh.json',
    'New Valley' => 'NewValleyData/new-valley.json',
    'Red Sea' => 'RedSeaData/red-sea.json',
];

function normalizeArabicName($name) {
    // Remove common prefixes and normalize
    $name = str_replace(['مستشفى ', 'مستشفي ', 'مستشفيات ', 'معمل ', 'د. ', 'د/', 'صيدلية ', 'صيدليات ', 'مركز '], '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

function findFacilityInJson($facilityNameAr, $category, $jsonData) {
    foreach ($jsonData['medical_directory'] as $cat) {
        if (strpos($cat['category']['ar'], $category) !== false || strpos($cat['category']['en'], $category) !== false) {
            foreach ($cat['facilities'] ?? [] as $facility) {
                $nameAr = $facility['name']['ar'] ?? '';
                if (stripos($nameAr, $facilityNameAr) !== false || stripos($facilityNameAr, $nameAr) !== false) {
                    return $facility;
                }
                // Try normalized comparison
                if (normalizeArabicName($nameAr) === normalizeArabicName($facilityNameAr)) {
                    return $facility;
                }
            }
        }
    }
    return null;
}

echo "=== COMPARISON REPORT: Part 3 Data ===\n\n";

$missingData = [];
$allMatch = true;

foreach ($providedData as $govName => $categories) {
    $jsonFile = __DIR__ . '/' . $existingFiles[$govName];
    if (!file_exists($jsonFile)) {
        echo "❌ File not found: $jsonFile\n";
        continue;
    }
    
    $jsonContent = json_decode(file_get_contents($jsonFile), true);
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "GOVERNORATE: $govName\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($categories as $category => $facilities) {
        echo "  Category: $category\n";
        $categoryMatch = true;
        
        foreach ($facilities as $key => $facilityData) {
            $nameAr = $facilityData['name'] ?? $key;
            // Extract main name part for searching
            $searchName = preg_replace('/\(.*?\)/', '', $nameAr);
            $searchName = str_replace(['مستشفى ', 'مستشفي ', 'معمل ', 'د. ', 'د/', 'صيدلية ', 'مركز '], '', $searchName);
            
            $found = findFacilityInJson($searchName, $category, $jsonContent);
            
            if (!$found) {
                echo "    ❌ MISSING: $nameAr\n";
                if (!isset($missingData[$govName])) {
                    $missingData[$govName] = [];
                }
                if (!isset($missingData[$govName][$category])) {
                    $missingData[$govName][$category] = [];
                }
                $missingData[$govName][$category][] = $facilityData;
                $categoryMatch = false;
                $allMatch = false;
            } else {
                echo "    ✅ Found: $nameAr\n";
            }
        }
        
        if ($categoryMatch) {
            echo "    ✅ All facilities in this category are present\n";
        }
        echo "\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

if ($allMatch) {
    echo "✅ All provided data exists in the JSON files!\n";
} else {
    echo "❌ Missing data found. Details:\n\n";
    
    foreach ($missingData as $gov => $categories) {
        echo "Governorate: $gov\n";
        foreach ($categories as $cat => $facilities) {
            echo "  Category: $cat\n";
            foreach ($facilities as $facility) {
                echo "    - " . ($facility['name'] ?? 'N/A') . "\n";
            }
        }
        echo "\n";
    }
    
    // Output JSON of missing data
    file_put_contents(__DIR__ . '/missing_part3_data.json', json_encode($missingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Missing data saved to: missing_part3_data.json\n";
}

