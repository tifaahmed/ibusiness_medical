<?php

namespace App\Services\FacilityMigration;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

/**
 * Converts an xlsx/csv spreadsheet into the migration zip format
 * (manifest.json + data/facilities.json) that FacilityMigrationImporter expects.
 */
class XlsxToMigrationZip
{
    /**
     * Convert a spreadsheet file to a migration package zip.
     *
     * @return string path to the generated zip file
     */
    public function convert(string $spreadsheetPath): string
    {
        $extension = strtolower(pathinfo($spreadsheetPath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader;
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
        } else {
            $reader = IOFactory::createReaderForFile($spreadsheetPath);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($spreadsheetPath);

        $facilityRows = $this->parseFacilitySheet($spreadsheet);
        $branchRows = $this->parseBranchSheet($spreadsheet);

        $spreadsheet->disconnectWorksheets();

        $branchesByFacility = [];
        foreach ($branchRows as $branch) {
            $key = mb_strtolower(trim($branch['facility_name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $branchesByFacility[$key][] = [
                'name' => [
                    'en' => $branch['name'] ?: null,
                    'ar' => $branch['name_ar'] ?: null,
                ],
                'address' => [
                    'en' => $branch['address'] ?: null,
                    'ar' => $branch['address_ar'] ?: null,
                ],
                'phone' => $branch['phone'] ?: null,
                'governorate' => $branch['governorate'] ?: null,
                'city' => $branch['city'] ?: null,
                'latitude' => $branch['latitude'] !== '' ? (float) $branch['latitude'] : null,
                'longitude' => $branch['longitude'] !== '' ? (float) $branch['longitude'] : null,
            ];
        }

        $facilities = [];
        foreach ($facilityRows as $row) {
            $nameEn = $row['name'] ?? '';
            $nameAr = $row['name_ar'] ?? '';
            $slug = $row['slug'] ?: \Illuminate\Support\Str::slug($nameEn);

            $facilityKey = mb_strtolower(trim($nameEn));
            $facilities[] = [
                'slug' => $slug,
                'name' => [
                    'en' => $nameEn ?: null,
                    'ar' => $nameAr ?: null,
                ],
                'description' => [],
                'facility_type' => $row['facility_type'] ?: null,
                'governorate' => $row['governorate'] ?: null,
                'city' => $row['city'] ?: null,
                'latitude' => $row['latitude'] !== '' ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== '' ? (float) $row['longitude'] : null,
                'branches' => $branchesByFacility[$facilityKey] ?? [],
            ];
        }

        $payload = [
            'format' => 'ibusiness-medical/facility-migration',
            'format_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'label' => 'Spreadsheet import',
                'site_url' => config('app.url'),
            ],
            'lookups' => [
                'facility_types' => $this->getFacilityTypes(),
                'governorates' => $this->getGovernorates(),
                'cities' => $this->getCities(),
                'sales' => [],
                'tags' => [],
            ],
            'facilities' => $facilities,
            'counts' => [
                'facilities' => count($facilities),
                'branches' => count($branchRows),
            ],
        ];

        $tmpDir = sys_get_temp_dir().'/facility_import_'.uniqid('', true);
        mkdir($tmpDir, 0775, true);
        @mkdir($tmpDir.'/data', 0775, true);

        file_put_contents(
            $tmpDir.'/data/facilities.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $zipPath = $tmpDir.'/import.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($tmpDir.'/data/facilities.json', 'data/facilities.json');
        $zip->addFromString('manifest.json', json_encode([
            'format' => 'ibusiness-medical/facility-import',
            'format_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'counts' => $payload['counts'],
        ], JSON_PRETTY_PRINT));
        $zip->close();

        return $zipPath;
    }

    private function parseFacilitySheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Facilities') ?? $spreadsheet->getActiveSheet();

        return $this->extractRows($sheet, [
            'name' => ['name'],
            'name_ar' => ['name (ar)', 'name_ar', 'arabic name'],
            'slug' => ['slug'],
            'facility_type' => ['facility type', 'facility_type', 'type'],
            'governorate' => ['governorate'],
            'city' => ['city'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'long'],
        ]);
    }

    private function parseBranchSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Branches');
        if (! $sheet) {
            return [];
        }

        return $this->extractRows($sheet, [
            'facility_name' => ['facility name', 'facility'],
            'name' => ['branch name', 'name'],
            'name_ar' => ['branch name (ar)', 'name_ar', 'arabic name'],
            'address' => ['address'],
            'address_ar' => ['address (ar)', 'address_ar'],
            'phone' => ['phone', 'phones'],
            'governorate' => ['governorate'],
            'city' => ['city'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'long'],
        ]);
    }

    private function extractRows($sheet, array $columnAliases): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        // Find header row
        $headerRow = null;
        for ($r = 1; $r <= min($highestRow, 10); $r++) {
            for ($col = 'A'; $col <= $highestCol; $col++) {
                $val = mb_strtolower(trim((string) $sheet->getCell("{$col}{$r}")->getValue()));
                if (in_array($val, ['name', '#'], true)) {
                    $headerRow = $r;
                    break 2;
                }
            }
        }

        if ($headerRow === null) {
            return [];
        }

        // Build column map
        $headerMap = [];
        for ($col = 'A'; $col <= $highestCol; $col++) {
            $val = trim((string) $sheet->getCell("{$col}{$headerRow}")->getValue());
            if ($val !== '') {
                $headerMap[mb_strtolower($val)] = $col;
            }
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $first = trim((string) $sheet->getCell("A{$r}")->getValue());
            if ($first === '' || str_starts_with(mb_strtoupper($first), 'END')) {
                continue;
            }

            $row = [];
            foreach ($columnAliases as $key => $candidates) {
                $row[$key] = '';
                foreach ($candidates as $candidate) {
                    if (isset($headerMap[mb_strtolower($candidate)])) {
                        $v = $sheet->getCell("{$headerMap[mb_strtolower($candidate)]}{$r}")->getValue();
                        $row[$key] = trim((string) ($v ?? ''));
                        break;
                    }
                }
            }

            if (collect($row)->filter(fn ($v) => $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function getFacilityTypes(): array
    {
        return \App\Models\FacilityType::all()->map(fn ($t) => [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->getTranslations('name'),
        ])->toArray();
    }

    private function getGovernorates(): array
    {
        return \App\Models\Governorate::all()->map(fn ($g) => [
            'id' => $g->id,
            'slug' => $g->slug,
            'name' => $g->getTranslations('name'),
        ])->toArray();
    }

    private function getCities(): array
    {
        return \App\Models\City::all()->map(fn ($c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->getTranslations('name'),
            'governorate_id' => $c->governorate_id,
        ])->toArray();
    }
}
