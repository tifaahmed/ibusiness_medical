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
                'phone' => $this->phoneList($branch['phone'] ?? null),
                'governorate' => $this->nameRef($branch['governorate'] ?? null),
                'city' => $this->nameRef($branch['city'] ?? null),
                'latitude' => $branch['latitude'] !== '' ? (float) $branch['latitude'] : null,
                'longitude' => $branch['longitude'] !== '' ? (float) $branch['longitude'] : null,
                'google_location_url' => $branch['google_location_url'] ?: null,
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
                'facility_type' => $this->nameRef($row['facility_type'] ?? null),
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

    /**
     * A lookup column is a single cell of text, but the importer and the import
     * preview screen both read lookups as { slug, name: { en, ar } } — a bare
     * string blows up on both sides.
     *
     * @return array<string, mixed>|null
     */
    private function nameRef(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // An Arabic cell must land in the ar slot or it will never match an
        // existing lookup row — and would create a duplicate named in Arabic
        // under the en key.
        $locale = preg_match('/\p{Arabic}/u', $value) ? 'ar' : 'en';

        return [
            'slug' => \Illuminate\Support\Str::slug($value) ?: null,
            'name' => [$locale => $value],
        ];
    }

    /**
     * A branch holds a list of phones; one cell can carry several, separated by
     * a newline, comma, semicolon or pipe.
     *
     * @return array<int, string>|null
     */
    private function phoneList(?string $value): ?array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,;|]+/', (string) $value)),
            fn ($p) => $p !== ''
        ));

        return $parts ?: null;
    }

    private function parseFacilitySheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Facilities') ?? $spreadsheet->getActiveSheet();

        return $this->extractRows($sheet, [
            'name' => ['name'],
            'name_ar' => ['name (ar)', 'name_ar', 'arabic name'],
            'slug' => ['slug'],
            'facility_type' => ['facility type', 'facility_type', 'type'],
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
            'google_location_url' => [
                'google location url', 'google_location_url', 'google url', 'location url',
                'map url', 'google maps url', 'google map url', 'google maps link', 'map link',
                'google maps', 'google map', 'google location',
            ],
        ]);
    }

    /**
     * One column heading, reduced to the words in it. A heading and the alias
     * it is meant to match are often the same words dressed differently — the
     * template writes "Google Location URL" while the alias reads
     * "google_location_url" — and comparing the raw labels means a filled-in
     * column is read as empty. Spaces, underscores, dashes and brackets all
     * become one separator, so every dressing of the same heading lands here.
     */
    private function headerKey(string $label): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(trim($label))));
    }

    private function extractRows($sheet, array $columnAliases): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        // Build a flat set of known header labels from the alias map
        $knownHeaders = [];
        foreach ($columnAliases as $candidates) {
            foreach ($candidates as $c) {
                $knownHeaders[$this->headerKey($c)] = true;
            }
        }

        // Find header row — match any known alias or '#'
        $headerRow = null;
        for ($r = 1; $r <= min($highestRow, 10); $r++) {
            for ($col = 'A'; $col <= $highestCol; $col++) {
                $val = trim((string) $sheet->getCell("{$col}{$r}")->getValue());
                if ($val === '#' || isset($knownHeaders[$this->headerKey($val)])) {
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
                $headerMap[$this->headerKey($val)] = $col;
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
                    $column = $headerMap[$this->headerKey($candidate)] ?? null;
                    if ($column !== null) {
                        $v = $sheet->getCell("{$column}{$r}")->getValue();
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
        $governorateSlugs = \App\Models\Governorate::pluck('slug', 'id');

        return \App\Models\City::all()->map(fn ($c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->getTranslations('name'),
            'governorate_id' => $c->governorate_id,
            // The importer attaches a newly created city to the governorate
            // named here before falling back to the branch's own.
            'governorate_slug' => $governorateSlugs[$c->governorate_id] ?? null,
        ])->toArray();
    }
}
