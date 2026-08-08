<?php

namespace Database\Seeders\AlexandriaData;

use DOMDocument;
use DOMXPath;

class AlexandriaDataParser
{
    protected $htmlContent;
    protected $dom;
    protected $xpath;

    public function __construct(string $htmlFilePath)
    {
        if (!file_exists($htmlFilePath)) {
            throw new \Exception("HTML file not found: {$htmlFilePath}");
        }

        $this->htmlContent = file_get_contents($htmlFilePath);
        
        // Google Sheets exports HTML in a specific format - try to handle it
        // The HTML might be minified on a single line
        
        // Load HTML with proper encoding
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        
        // Try loading with different methods
        $loaded = @$this->dom->loadHTML('<?xml encoding="UTF-8">' . $this->htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        if (!$loaded) {
            // Fallback: try loading without the XML declaration
            $this->dom->loadHTML(mb_convert_encoding($this->htmlContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }
        
        libxml_clear_errors();

        $this->xpath = new DOMXPath($this->dom);
    }

    /**
     * Parse the HTML and extract facility data with branches
     * 
     * @return array Array of parsed facilities with their data and branches
     */
    public function parseFacilities(): array
    {
        $facilities = [];
        
        // Method 1: Try finding table rows
        $rows = $this->xpath->query('//tr');
        
        // Method 2: Try finding table elements first
        $tables = $this->xpath->query('//table');
        
        // Method 3: Try finding divs with class containing 'waffle' (Google Sheets format)
        $waffleRows = $this->xpath->query('//div[contains(@class, "waffle")]//tr');
        
        if ($waffleRows->length > 0) {
            $rows = $waffleRows;
        } elseif ($tables->length > 0) {
            // Get rows from first table
            $rows = $this->xpath->query('.//tr', $tables->item(0));
        }
        
        // Method 4: Try parsing from raw HTML using regex as fallback
        if ($rows->length === 0) {
            return $this->parseFromRawHtml();
        }

        $headers = [];
        $headerRow = true;
        $skipFirstRows = 0; // Skip title/header rows

        // Collect all row data first
        $allRowData = [];
        
        foreach ($rows as $rowIndex => $row) {
            $cells = $row->getElementsByTagName('td');
            
            // Also try th elements
            if ($cells->length === 0) {
                $cells = $row->getElementsByTagName('th');
            }
            
            if ($cells->length === 0) {
                continue;
            }

            // Collect cell values
            $cellValues = [];
            foreach ($cells as $cell) {
                $text = trim($cell->textContent);
                // Remove extra whitespace
                $text = preg_replace('/\s+/', ' ', $text);
                $cellValues[] = $text;
            }

            // Skip completely empty rows
            if (empty(array_filter($cellValues))) {
                continue;
            }

            // Skip header rows (rows with common header text)
            $firstCellLower = strtolower($cellValues[0] ?? '');
            if ($headerRow && (
                empty($firstCellLower) ||
                stripos($firstCellLower, 'نوع') !== false ||
                stripos($firstCellLower, 'اسم') !== false ||
                stripos($firstCellLower, 'type') !== false ||
                stripos($firstCellLower, 'name') !== false ||
                $skipFirstRows < 1 // Skip first 1-2 rows
            )) {
                if ($headerRow) {
                    $headers = $cellValues;
                    $headerRow = false;
                }
                $skipFirstRows++;
                continue;
            }

            // If we have headers, use them; otherwise use index-based
            $rowData = [];
            if (!empty($headers) && count($headers) > 0) {
                foreach ($cellValues as $index => $value) {
                    $header = $headers[$index] ?? "column_{$index}";
                    $rowData[$header] = $value;
                }
            } else {
                // Use index-based approach
                for ($i = 0; $i < count($cellValues); $i++) {
                    $rowData["column_{$i}"] = $cellValues[$i];
                }
            }
            
            if (!empty($rowData)) {
                $allRowData[] = $rowData;
            }
        }

        // Now group rows by facility (detect branches)
        $currentFacility = null;
        $currentFacilityType = '';
        
        foreach ($allRowData as $rowIndex => $rowData) {
            // Check if this row has a facility name
            $hasFacilityName = $this->hasFacilityName($rowData);
            
            if ($hasFacilityName) {
                // This is a new facility - save previous if exists
                if ($currentFacility !== null) {
                    $facilities[] = $currentFacility;
                }
                
                // Start new facility
                $facility = $this->extractFacilityData($rowData);
                if ($facility && !empty($facility['name_ar'])) {
                    // Update current facility type
                    if (!empty($facility['facility_type_ar'])) {
                        $currentFacilityType = $facility['facility_type_ar'];
                    }
                    $facility['branches'] = [];
                    
                    // IMPORTANT: If facility row has address and/or phone, create first branch from it
                    // This ensures the facility's own location becomes the first branch
                    $facilityAddressAr = $facility['address_ar'] ?? '';
                    $facilityPhone = $facility['phone'] ?? '';
                    
                    // Check if facility row has multiple phones - create branches for additional phones
                    $allPhones = $this->extractAllPhones($rowData);
                    if (count($allPhones) > 1) {
                        // First phone goes to main facility
                        $facility['phone'] = $allPhones[0];
                        // Create first branch from facility's address and first phone
                        if (!empty($facilityAddressAr) || !empty($allPhones[0])) {
                            $facility['branches'][] = [
                                'address_ar' => $facilityAddressAr,
                                'address_en' => $facility['address_en'] ?? '',
                                'phone' => $allPhones[0],
                            ];
                        }
                        // Rest become additional branches
                        for ($i = 1; $i < count($allPhones); $i++) {
                            $facility['branches'][] = [
                                'address_ar' => $facilityAddressAr,
                                'address_en' => $facility['address_en'] ?? '',
                                'phone' => $allPhones[$i],
                            ];
                        }
                    } elseif (!empty($facilityAddressAr) || !empty($facilityPhone)) {
                        // Facility has address or phone - create first branch from facility data
                        $facility['branches'][] = [
                            'address_ar' => $facilityAddressAr,
                            'address_en' => $facility['address_en'] ?? '',
                            'phone' => $facilityPhone,
                        ];
                    }
                    
                    $currentFacility = $facility;
                } else {
                    $currentFacility = null;
                }
            } else {
                // This might be a branch row (has address/phone but no facility name)
                // OR it could be a continuation row of the current facility
                $branchData = $this->extractBranchData($rowData);
                
                if ($branchData && $currentFacility !== null) {
                    // This is definitely a branch row - add it to current facility
                    // Check if this row has multiple phones
                    $allPhones = $this->extractAllPhones($rowData);
                    if (count($allPhones) > 1) {
                        // Multiple phones - create a branch for each
                        if (!empty($branchData['address_ar'])) {
                            // Same address, multiple phones = multiple branches
                            foreach ($allPhones as $phone) {
                                $currentFacility['branches'][] = [
                                    'address_ar' => $branchData['address_ar'],
                                    'address_en' => $branchData['address_en'],
                                    'phone' => $phone,
                                ];
                            }
                        } else {
                            // Multiple phones, no address = multiple branches with same address as facility
                            foreach ($allPhones as $phone) {
                                $currentFacility['branches'][] = [
                                    'address_ar' => $currentFacility['address_ar'] ?? '',
                                    'address_en' => $currentFacility['address_en'] ?? '',
                                    'phone' => $phone,
                                ];
                            }
                        }
                    } else {
                        // Single branch entry - add it
                        $currentFacility['branches'][] = $branchData;
                    }
                } elseif (!$hasFacilityName && !empty($rowData)) {
                    // Row doesn't have facility name but has data
                    // Check if it's a branch row even without current facility
                    // This handles cases where branch detection failed earlier
                    $potentialBranch = $this->extractBranchData($rowData);
                    if ($potentialBranch && $currentFacility !== null) {
                        // Add as branch
                        $currentFacility['branches'][] = $potentialBranch;
                    } elseif ($this->isFacilityTypeHeader($rowData)) {
                        // This might be a facility type header
                        $facilityType = $this->extractFacilityType($rowData);
                        if ($facilityType) {
                            $currentFacilityType = $facilityType;
                        }
                    }
                }
            }
        }
        
        // Don't forget the last facility
        if ($currentFacility !== null) {
            $facilities[] = $currentFacility;
        }

        return $facilities;
    }

    /**
     * Parse from raw HTML using regex (fallback method)
     */
    protected function parseFromRawHtml(): array
    {
        $facilities = [];
        
        // Extract text from td elements using regex
        preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $this->htmlContent, $matches);
        
        if (empty($matches[1])) {
            return [];
        }

        // Clean up the matches
        $cells = array_map(function($cell) {
            $cell = strip_tags($cell);
            $cell = html_entity_decode($cell, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cell = trim($cell);
            $cell = preg_replace('/\s+/', ' ', $cell);
            return $cell;
        }, $matches[1]);

        // Filter out empty cells
        $cells = array_filter($cells, function($cell) {
            return !empty($cell) && strlen($cell) > 1;
        });

        // Reset array keys
        $cells = array_values($cells);

        // Group into rows (assuming 4-6 columns)
        $columnsPerRow = 5;
        $rowCount = ceil(count($cells) / $columnsPerRow);

        for ($i = 0; $i < $rowCount; $i++) {
            $start = $i * $columnsPerRow;
            $rowData = array_slice($cells, $start, $columnsPerRow);
            
            if (count($rowData) >= 2 && !empty($rowData[1])) {
                // Skip header rows
                $firstCell = strtolower($rowData[0] ?? '');
                if (stripos($firstCell, 'نوع') !== false || 
                    stripos($firstCell, 'اسم') !== false ||
                    stripos($firstCell, 'type') !== false) {
                    continue;
                }

                $facility = $this->extractFacilityDataFromArray($rowData);
                if ($facility && !empty($facility['name_ar'])) {
                    $facilities[] = $facility;
                }
            }
        }

        return $facilities;
    }

    /**
     * Parse from individual cells when row structure is not clear
     */
    protected function parseFromCells($cells): array
    {
        $facilities = [];
        $data = [];
        
        foreach ($cells as $cell) {
            $text = trim($cell->textContent);
            if (!empty($text)) {
                $data[] = $text;
            }
        }

        // Try to group data into rows (assuming 4-6 columns per facility)
        $columnsPerRow = 5; // Adjust based on your data structure
        for ($i = 0; $i < count($data); $i += $columnsPerRow) {
            $rowData = array_slice($data, $i, $columnsPerRow);
            if (count($rowData) >= 2) {
                $facility = $this->extractFacilityDataFromArray($rowData);
                if ($facility) {
                    $facilities[] = $facility;
                }
            }
        }

        return $facilities;
    }

    /**
     * Extract facility data from row array
     */
    protected function extractFacilityData(array $rowData): ?array
    {
        // Common patterns in Arabic data:
        // Column 0: Facility Type (نوع المنشأة)
        // Column 1: Facility Name (اسم المنشأة)
        // Column 2: Address (العنوان)
        // Column 3: Phone (الهاتف)
        // Column 4: Branch info or additional data

        $values = array_values($rowData);
        
        // Try to find facility name in any column
        $facilityNameAr = '';
        $facilityTypeAr = '';
        $addressAr = '';
        $phone = '';
        $branchInfo = '';

        // Look for facility name (usually has Arabic characters and is not a header)
        foreach ($values as $index => $value) {
            $value = trim($value);
            $valueLower = mb_strtolower($value, 'UTF-8');
            
            // Skip if it looks like a header
            if (stripos($valueLower, 'نوع') !== false || 
                stripos($valueLower, 'اسم') !== false ||
                stripos($valueLower, 'عنوان') !== false ||
                stripos($valueLower, 'هاتف') !== false ||
                stripos($valueLower, 'type') !== false ||
                stripos($valueLower, 'name') !== false ||
                stripos($valueLower, 'address') !== false ||
                stripos($valueLower, 'phone') !== false) {
                continue;
            }

            // If we haven't found facility name and this looks like one (has Arabic chars)
            if (empty($facilityNameAr) && preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
                // Check if it might be facility type (shorter, common types)
                if (strlen($value) < 30 && (
                    stripos($value, 'مستشفى') !== false ||
                    stripos($value, 'صيدلية') !== false ||
                    stripos($value, 'مختبر') !== false ||
                    stripos($value, 'عيادة') !== false ||
                    stripos($value, 'مركز') !== false
                )) {
                    $facilityTypeAr = $value;
                } else {
                    $facilityNameAr = $value;
                }
            }
            
            // Check for phone (has digits)
            if (empty($phone) && preg_match('/[\d\+\-\(\)]+/', $value) && strlen(preg_replace('/[^\d]/', '', $value)) >= 7) {
                $phone = $value;
            }
            
            // Check for address (usually longer text with Arabic)
            if (empty($addressAr) && preg_match('/[\x{0600}-\x{06FF}]/u', $value) && strlen($value) > 20 && $value !== $facilityNameAr) {
                $addressAr = $value;
            }
        }

        // If we have headers, try to use them
        if (!empty($rowData) && count($values) > 0) {
            // Check if rowData has named keys (from headers)
            $keys = array_keys($rowData);
            if (!is_numeric($keys[0])) {
                // Has header-based keys
                foreach ($rowData as $key => $value) {
                    $keyLower = mb_strtolower($key, 'UTF-8');
                    $value = trim($value);
                    
                    if (stripos($keyLower, 'نوع') !== false || stripos($keyLower, 'type') !== false) {
                        $facilityTypeAr = $value;
                    } elseif (stripos($keyLower, 'اسم') !== false || stripos($keyLower, 'name') !== false) {
                        $facilityNameAr = $value;
                    } elseif (stripos($keyLower, 'عنوان') !== false || stripos($keyLower, 'address') !== false) {
                        $addressAr = $value;
                    } elseif (stripos($keyLower, 'هاتف') !== false || stripos($keyLower, 'phone') !== false) {
                        $phone = $value;
                    }
                }
            } else {
                // Use position-based assignment
                $facilityTypeAr = $values[0] ?? $facilityTypeAr;
                $facilityNameAr = $values[1] ?? $facilityNameAr;
                $addressAr = $values[2] ?? $addressAr;
                $phone = $values[3] ?? $phone;
                $branchInfo = $values[4] ?? $branchInfo;
            }
        }

        // Skip if no facility name found
        if (empty($facilityNameAr)) {
            return null;
        }

        return [
            'facility_type_ar' => $facilityTypeAr,
            'facility_type_en' => $this->translateFacilityType($facilityTypeAr),
            'name_ar' => $facilityNameAr,
            'name_en' => $this->translateName($facilityNameAr),
            'address_ar' => $addressAr,
            'address_en' => $this->translateAddress($addressAr),
            'phone' => $this->cleanPhone($phone),
            'branch_info' => $branchInfo,
            'branches' => [], // Will be populated during parsing
        ];
    }

    /**
     * Check if a row has a facility name
     * A facility name is more specific than just an address or location name
     */
    protected function hasFacilityName(array $rowData): bool
    {
        $values = array_values($rowData);
        
        // Check if first column (or early columns) has a facility name
        // Facility names are typically in the first or second column
        // Branch rows typically have address in first column, phone in later columns
        
        $firstValue = '';
        $secondValue = '';
        
        if (count($values) > 0) {
            $firstValue = trim($values[0] ?? '');
        }
        if (count($values) > 1) {
            $secondValue = trim($values[1] ?? '');
        }
        
        // Check first column for facility name indicators
        if (!empty($firstValue) && preg_match('/[\x{0600}-\x{06FF}]/u', $firstValue)) {
            $firstValueLower = mb_strtolower($firstValue, 'UTF-8');
            
            // Skip headers
            if (stripos($firstValueLower, 'نوع') !== false || 
                stripos($firstValueLower, 'اسم') !== false ||
                stripos($firstValueLower, 'عنوان') !== false ||
                stripos($firstValueLower, 'هاتف') !== false ||
                stripos($firstValueLower, 'type') !== false ||
                stripos($firstValueLower, 'name') !== false ||
                stripos($firstValueLower, 'address') !== false ||
                stripos($firstValueLower, 'phone') !== false) {
                return false;
            }
            
            // Skip if it's just a facility type header
            if (strlen($firstValue) < 25 && (
                stripos($firstValue, 'مستشفيات') !== false ||
                stripos($firstValue, 'صيدليات') !== false ||
                stripos($firstValue, 'مراكز') !== false ||
                stripos($firstValue, 'عيادات') !== false
            )) {
                return false;
            }
            
            // Skip if it looks like an address (contains street indicators, numbers, or location words)
            $addressIndicators = ['ش', 'شارع', 'طريق', 'منطقة', 'حي', 'قرية', 'الساحل', 'شمالى', 'جنوب'];
            $hasAddressIndicator = false;
            foreach ($addressIndicators as $indicator) {
                if (stripos($firstValue, $indicator) !== false || preg_match('/\d+.*ش/u', $firstValue)) {
                    $hasAddressIndicator = true;
                    break;
                }
            }
            
            // If it has address indicators and no facility name indicators, it's likely an address/branch
            if ($hasAddressIndicator && 
                stripos($firstValue, 'مستشفى') === false &&
                stripos($firstValue, 'صيدلية') === false &&
                stripos($firstValue, 'مختبر') === false &&
                stripos($firstValue, 'عيادة') === false &&
                stripos($firstValue, 'مركز') === false &&
                stripos($firstValue, 'معهد') === false &&
                stripos($firstValue, 'نظارات') === false) {
                return false; // This looks like an address/branch, not a facility name
            }
            
            // If it's a facility name, it should contain facility indicators
            if (stripos($firstValue, 'مستشفى') !== false ||
                stripos($firstValue, 'صيدلية') !== false ||
                stripos($firstValue, 'مختبر') !== false ||
                stripos($firstValue, 'عيادة') !== false ||
                stripos($firstValue, 'مركز') !== false ||
                stripos($firstValue, 'معهد') !== false ||
                stripos($firstValue, 'نظارات') !== false ||
                stripos($firstValue, 'مستوصف') !== false ||
                stripos($firstValue, 'عيادة') !== false ||
                (strlen($firstValue) > 20 && !$hasAddressIndicator)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract all phone numbers from a row
     */
    protected function extractAllPhones(array $rowData): array
    {
        $values = array_values($rowData);
        $phones = [];
        
        foreach ($values as $value) {
            $value = trim($value);
            if (empty($value)) {
                continue;
            }
            
            // Check for phone (has digits, at least 7 digits)
            if (preg_match('/[\d\+\-\(\)]+/', $value) && strlen(preg_replace('/[^\d]/', '', $value)) >= 7) {
                $cleanedPhone = $this->cleanPhone($value);
                if (!empty($cleanedPhone) && !in_array($cleanedPhone, $phones)) {
                    $phones[] = $cleanedPhone;
                }
            }
        }
        
        return $phones;
    }
    
    /**
     * Extract branch data from a row (address/phone but no facility name)
     */
    protected function extractBranchData(array $rowData): ?array
    {
        $values = array_values($rowData);
        $addressAr = '';
        $phone = '';
        
        // First, try to get the first phone
        $allPhones = $this->extractAllPhones($rowData);
        if (!empty($allPhones)) {
            $phone = $allPhones[0];
        }
        
        foreach ($values as $value) {
            $value = trim($value);
            if (empty($value)) {
                continue;
            }
            
            // Skip phones (already extracted)
            if (preg_match('/^[\d\+\-\(\)\s]+$/', $value) && strlen(preg_replace('/[^\d]/', '', $value)) >= 7) {
                continue;
            }
            
            // Check for address (has Arabic, longer text, not a phone)
            if (empty($addressAr) && preg_match('/[\x{0600}-\x{06FF}]/u', $value) && 
                strlen($value) > 10 && 
                !preg_match('/^[\d\+\-\(\)\s]+$/', $value)) {
                $addressAr = $value;
                continue;
            }
        }
        
        // Return branch data if we found at least address or phone
        if (!empty($addressAr) || !empty($phone)) {
            return [
                'address_ar' => $addressAr,
                'address_en' => $this->translateAddress($addressAr),
                'phone' => $phone,
            ];
        }
        
        return null;
    }
    
    /**
     * Check if a row is a facility type header
     */
    protected function isFacilityTypeHeader(array $rowData): bool
    {
        $values = array_values($rowData);
        
        foreach ($values as $value) {
            $value = trim($value);
            if (empty($value)) {
                continue;
            }
            
            $valueLower = mb_strtolower($value, 'UTF-8');
            
            // Check if it's a facility type header
            if (stripos($valueLower, 'مراكز ومستشفيات') !== false ||
                stripos($valueLower, 'مستشفيات') !== false ||
                stripos($valueLower, 'صيدليات') !== false ||
                stripos($valueLower, 'مراكز') !== false ||
                stripos($valueLower, 'عيادات') !== false ||
                stripos($valueLower, 'معامل') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract facility type from header row
     */
    protected function extractFacilityType(array $rowData): ?string
    {
        $values = array_values($rowData);
        
        foreach ($values as $value) {
            $value = trim($value);
            if (empty($value)) {
                continue;
            }
            
            if ($this->isFacilityTypeHeader(['column_0' => $value])) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Extract facility data from simple array
     */
    protected function extractFacilityDataFromArray(array $rowData): ?array
    {
        if (empty($rowData) || count($rowData) < 2) {
            return null;
        }

        return [
            'facility_type_ar' => $rowData[0] ?? '',
            'facility_type_en' => $this->translateFacilityType($rowData[0] ?? ''),
            'name_ar' => $rowData[1] ?? '',
            'name_en' => $this->translateName($rowData[1] ?? ''),
            'address_ar' => $rowData[2] ?? '',
            'address_en' => $this->translateAddress($rowData[2] ?? ''),
            'phone' => $this->cleanPhone($rowData[3] ?? ''),
            'branch_info' => $rowData[4] ?? '',
        ];
    }

    /**
     * Clean phone number
     */
    protected function cleanPhone(string $phone): string
    {
        // Remove non-numeric characters except + and -
        $phone = preg_replace('/[^\d+\-]/', '', $phone);
        return trim($phone);
    }

    /**
     * Translate facility type (simple mapping)
     */
    protected function translateFacilityType(string $ar): string
    {
        $translations = [
            'مستشفى' => 'Hospital',
            'مستشفيات' => 'Hospitals',
            'مستشفيات خاصة' => 'Private Hospitals',
            'مستشفى خاص' => 'Private Hospital',
            'صيدلية' => 'Pharmacy',
            'صيدليات' => 'Pharmacies',
            'مختبر' => 'Laboratory',
            'مختبرات' => 'Laboratories',
            'عيادة' => 'Clinic',
            'عيادات' => 'Clinics',
            'مركز طبي' => 'Medical Center',
            'مراكز طبية' => 'Medical Centers',
            'عيادة أسنان' => 'Dental Clinic',
            'عيادات أسنان' => 'Dental Clinics',
            'مركز أشعة' => 'Radiology Center',
            'مراكز أشعة' => 'Radiology Centers',
        ];

        return $translations[$ar] ?? $ar;
    }

    /**
     * Translate name from Arabic to English
     * Translates common medical terms and transliterates names
     */
    protected function translateName(string $ar): string
    {
        if (empty($ar)) {
            return '';
        }

        $translated = $ar;

        // Common medical facility translations
        $facilityTranslations = [
            'مستشفى' => 'Hospital',
            'مستشفيات' => 'Hospitals',
            'مستشفى خاص' => 'Private Hospital',
            'مستشفيات خاصة' => 'Private Hospitals',
            'صيدلية' => 'Pharmacy',
            'صيدليات' => 'Pharmacies',
            'مختبر' => 'Laboratory',
            'مختبرات' => 'Laboratories',
            'معمل' => 'Lab',
            'معامل' => 'Labs',
            'عيادة' => 'Clinic',
            'عيادات' => 'Clinics',
            'مركز' => 'Center',
            'مراكز' => 'Centers',
            'مركز طبي' => 'Medical Center',
            'مراكز طبية' => 'Medical Centers',
            'عيادة أسنان' => 'Dental Clinic',
            'عيادات أسنان' => 'Dental Clinics',
            'مركز أشعة' => 'Radiology Center',
            'مراكز أشعة' => 'Radiology Centers',
            'معهد' => 'Institute',
            'مستوصف' => 'Dispensary',
            'نظارات' => 'Optics',
            'بصريات' => 'Optics',
        ];

        // Replace facility types
        foreach ($facilityTranslations as $arabic => $english) {
            $translated = str_replace($arabic, $english, $translated);
        }

        // Common name patterns
        $namePatterns = [
            'الأمل' => 'Al-Amal',
            'الامل' => 'Al-Amal',
            'الأهلي' => 'Al-Ahly',
            'الاهلي' => 'Al-Ahly',
            'الجامعي' => 'University',
            'الخاص' => 'Private',
            'التخصصي' => 'Specialized',
            'التخصصى' => 'Specialized',
            'العسكري' => 'Military',
            'الوطني' => 'National',
            'السلام' => 'Al-Salam',
            'الخير' => 'Al-Khair',
            'البركة' => 'Al-Baraka',
            'الفرقان' => 'Al-Forqan',
            'الفرسان' => 'Al-Forsan',
            'الزهراء' => 'Al-Zahra',
            'الزهرا' => 'Al-Zahra',
            'النجاح' => 'Al-Najah',
            'الروماني' => 'Al-Romani',
            'الرومانى' => 'Al-Romani',
            'الغندور' => 'Al-Ghandour',
            'العزبي' => 'Al-Azbi',
            'العزبى' => 'Al-Azbi',
            'البرج' => 'Al-Borg',
            'الشمس' => 'Al-Shams',
            'الايمان' => 'Al-Eman',
            'الايمان' => 'Al-Eman',
            'الغنيمي' => 'Al-Ghoneimi',
            'الغنيمى' => 'Al-Ghoneimi',
        ];

        // Replace common name patterns
        foreach ($namePatterns as $arabic => $english) {
            $translated = str_replace($arabic, $english, $translated);
        }

        // If still contains Arabic characters, transliterate the remaining parts
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $translated)) {
            // Remove remaining Arabic characters and clean up
            $translated = preg_replace('/[\x{0600}-\x{06FF}]+/u', '', $translated);
            $translated = trim($translated);
            $translated = preg_replace('/\s+/', ' ', $translated);
        }

        // Clean up extra spaces and dashes
        $translated = trim($translated);
        $translated = preg_replace('/\s+/', ' ', $translated);
        $translated = preg_replace('/\s*-\s*/', ' - ', $translated);

        return $translated;
    }

    /**
     * Translate address from Arabic to English
     * Translates common address terms and transliterates location names
     */
    protected function translateAddress(string $ar): string
    {
        if (empty($ar)) {
            return '';
        }

        $translated = $ar;

        // Common address term translations
        $addressTranslations = [
            'شارع' => 'St',
            'ش' => 'St',
            'طريق' => 'Road',
            'ميدان' => 'Square',
            'ساحة' => 'Square',
            'منطقة' => 'Area',
            'حي' => 'District',
            'حى' => 'District',
            'قرية' => 'Village',
            'مدينة' => 'City',
            'محافظة' => 'Governorate',
            'بجوار' => 'Next to',
            'امام' => 'In front of',
            'خلف' => 'Behind',
            'اعلى' => 'Above',
            'أعلى' => 'Above',
            'تحت' => 'Below',
            'الدور' => 'Floor',
            'الطابق' => 'Floor',
            'الدور الأرضي' => 'Ground Floor',
            'الدور الاول' => '1st Floor',
            'الدور الأول' => '1st Floor',
            'الدور الثاني' => '2nd Floor',
            'الدور الثانى' => '2nd Floor',
            'الدور الثالث' => '3rd Floor',
            'الدور الثالث' => '3rd Floor',
            'برج' => 'Tower',
            'عمارة' => 'Building',
            'مبنى' => 'Building',
            'مول' => 'Mall',
            'سنتر' => 'Center',
            'سنتر' => 'Center',
            'كورنيش' => 'Corniche',
            'الساحل' => 'Coast',
            'شمال' => 'North',
            'جنوب' => 'South',
            'شرق' => 'East',
            'غرب' => 'West',
        ];

        // Replace address terms
        foreach ($addressTranslations as $arabic => $english) {
            // Use word boundaries to avoid partial matches
            $translated = preg_replace('/\b' . preg_quote($arabic, '/') . '\b/u', $english, $translated);
        }

        // Common location name translations for Alexandria
        $locationTranslations = [
            'الاسكندرية' => 'Alexandria',
            'الاسكندرية' => 'Alexandria',
            'سموحة' => 'Smouha',
            'سيدى بشر' => 'Sidi Bishr',
            'سيدى جابر' => 'Sidi Gaber',
            'محطة قطار' => 'Railway Station',
            'محطة القطار' => 'Railway Station',
            'المحطة' => 'Station',
            'المنتزه' => 'El Montaza',
            'المنتزة' => 'El Montaza',
            'الرمل' => 'El Raml',
            'اللبان' => 'El Laban',
            'العجمي' => 'El Agamy',
            'العجمى' => 'El Agamy',
            'المنشية' => 'El Mansheya',
            'محرم بك' => 'Moharam Bek',
            'جليم' => 'Gleem',
            'ستانلي' => 'Stanley',
            'سيدي بشر' => 'Sidi Bishr',
            'سيدي جابر' => 'Sidi Gaber',
            'كفر عبده' => 'Kafr Abdo',
            'زيزينيا' => 'Zizinia',
            'زيزينيا' => 'Zizinia',
            'سابا باشا' => 'Saba Pasha',
            'سابا باشا' => 'Saba Pasha',
            'البيجو' => 'Peugeot',
            'البيجو' => 'Peugeot',
        ];

        // Replace location names
        foreach ($locationTranslations as $arabic => $english) {
            $translated = str_replace($arabic, $english, $translated);
        }

        // If still contains Arabic characters, transliterate numbers and clean up
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $translated)) {
            // Try to transliterate remaining Arabic numbers
            $arabicNumbers = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];
            foreach ($arabicNumbers as $arabic => $english) {
                $translated = str_replace($arabic, $english, $translated);
            }
            
            // Remove any remaining Arabic characters that couldn't be translated
            $translated = preg_replace('/[\x{0600}-\x{06FF}]+/u', '', $translated);
        }

        // Clean up extra spaces, dashes, and punctuation
        $translated = trim($translated);
        $translated = preg_replace('/\s+/', ' ', $translated);
        $translated = preg_replace('/\s*-\s*/', ' - ', $translated);
        $translated = preg_replace('/\s*,\s*/', ', ', $translated);
        $translated = preg_replace('/\s*\.\s*/', '. ', $translated);

        return $translated;
    }
}

