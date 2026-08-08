<?php

namespace App\Http\Controllers\Admin\Facility\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminFacilityImportPreviewController extends BaseController
{
    /**
     * Parse an uploaded CSV/XLSX, match each row to an existing facility (by slug,
     * then by name in any locale), validate, and compute a per-field diff for rows
     * that would be updated. Nothing is written to the database.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        ['facilities' => $rows, 'branches' => $branchesByKey] = $this->parseSpreadsheet(
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalExtension()
        );

        // Pre-fetch lookup tables once. Indexed by lowercased EN+AR name.
        $facilityTypes = $this->indexByTranslatedName(FacilityType::all());
        $governorates = $this->indexByTranslatedName(Governorate::all());
        $cities = $this->indexByTranslatedName(City::all());

        $preview = [];
        foreach ($rows as $i => $raw) {
            $parsed = $this->parseRow($raw, $facilityTypes, $governorates, $cities);
            $errors = $this->validateRow($parsed);

            $match = null;
            if (!empty($parsed['slug'])) {
                $match = Facility::with(['facilityType'])
                    ->where('slug', $parsed['slug'])
                    ->first();
            }
            if (!$match && !empty($parsed['name'])) {
                $needle = mb_strtolower(trim($parsed['name']));
                $match = Facility::with(['facilityType'])
                    ->where('name->en', $parsed['name'])
                    ->orWhere('name->ar', $parsed['name'])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$needle])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.ar"))) = ?', [$needle])
                    ->first();
            }

            $diff = $match ? $this->buildDiff($match, $parsed) : [];
            $hasChanges = collect($diff)->contains(fn($d) => $d['changed']);

            $status = !empty($errors) ? 'error' : ($match ? ($hasChanges ? 'update' : 'unchanged') : 'new');

            // Look up branches keyed by facility name or slug — both work.
            $branchKey = mb_strtolower(trim($parsed['name']));
            $slugKey = mb_strtolower(trim($parsed['slug']));
            $branches = $branchesByKey[$branchKey] ?? $branchesByKey[$slugKey] ?? [];

            $preview[] = [
                'index' => $i,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
                'status' => $status,
                'match' => $match ? [
                    'facility_id' => $match->id,
                    'slug' => $match->slug,
                    'name' => $match->getTranslation('name', app()->getLocale()) ?: $match->getTranslation('name', 'en'),
                ] : null,
                'diff' => $diff,
                'branches' => $branches,
            ];
        }

        return response()->json([
            'rows' => $preview,
            'facilityTypeOptions' => FacilityType::orderBy('id')->get()->map(fn($t) => [
                'value' => $t->id,
                'label' => $t->getTranslation('name', app()->getLocale()) ?: $t->getTranslation('name', 'en'),
            ])->values(),
            'governorateOptions' => Governorate::orderBy('id')->get()->map(fn($g) => [
                'value' => $g->id,
                'label' => $g->getTranslation('name', app()->getLocale()) ?: $g->getTranslation('name', 'en'),
            ])->values(),
            'cityOptions' => City::orderBy('id')->get()->map(fn($c) => [
                'value' => $c->id,
                'governorate_id' => $c->governorate_id,
                'label' => $c->getTranslation('name', app()->getLocale()) ?: $c->getTranslation('name', 'en'),
            ])->values(),
        ]);
    }

    /**
     * Read the spreadsheet and return rows as associative arrays keyed by
     * column name. Skips title/banner/filter rows by locating the row whose
     * column A is "#" and column B is "Name" — same shape the export emits.
     */
    private function parseSpreadsheet(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $facilitySheet = $spreadsheet->getSheetByName('Facilities') ?? $spreadsheet->getActiveSheet();
        $facilities = $this->extractRowsFromSheet(
            $facilitySheet,
            ['name'],
            [
                'name' => ['name'],
                'name_ar' => ['name (ar)', 'name_ar', 'arabic name'],
                'slug' => ['slug'],
                'facility_type' => ['facility type', 'facility_type', 'type'],
                'governorate' => ['governorate'],
                'city' => ['city'],
                'latitude' => ['latitude', 'lat'],
                'longitude' => ['longitude', 'lng', 'long'],
            ]
        );

        $branches = [];
        $branchSheet = $spreadsheet->getSheetByName('Branches');
        if ($branchSheet) {
            $branchRows = $this->extractRowsFromSheet(
                $branchSheet,
                ['branch name', 'name'],
                [
                    'facility_name' => ['facility name', 'facility'],
                    'facility_slug' => ['facility slug', 'slug'],
                    'name' => ['branch name', 'name'],
                    'name_ar' => ['branch name (ar)', 'name_ar', 'arabic name'],
                    'address' => ['address'],
                    'address_ar' => ['address (ar)', 'address_ar'],
                    'phone' => ['phone', 'phones'],
                    'governorate' => ['governorate'],
                    'city' => ['city'],
                    'latitude' => ['latitude', 'lat'],
                    'longitude' => ['longitude', 'lng', 'long'],
                ]
            );
            foreach ($branchRows as $b) {
                $facilityName = mb_strtolower(trim((string) $b['facility_name']));
                $facilitySlug = mb_strtolower(trim((string) $b['facility_slug']));
                $key = $facilityName !== '' ? $facilityName : $facilitySlug;
                if ($key === '') {
                    continue;
                }
                $branches[$key][] = [
                    'name' => $b['name'],
                    'name_ar' => $b['name_ar'] !== '' ? $b['name_ar'] : null,
                    'address' => $b['address'] !== '' ? $b['address'] : null,
                    'address_ar' => $b['address_ar'] !== '' ? $b['address_ar'] : null,
                    'phone' => $b['phone'] !== '' ? $b['phone'] : null,
                    'governorate' => $b['governorate'] !== '' ? $b['governorate'] : null,
                    'city' => $b['city'] !== '' ? $b['city'] : null,
                    'latitude' => $b['latitude'] !== '' ? (float) $b['latitude'] : null,
                    'longitude' => $b['longitude'] !== '' ? (float) $b['longitude'] : null,
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();
        return ['facilities' => $facilities, 'branches' => $branches];
    }

    /**
     * Locate a sheet's header row by looking for one of $signatureKeys appearing
     * in any column, then read all rows below into associative arrays keyed by
     * the names in $columnAliases. Stops at "END OF REPORT" footers.
     */
    private function extractRowsFromSheet($sheet, array $signatureKeys, array $columnAliases): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $signatureKeys = array_map('mb_strtolower', $signatureKeys);

        $headerRow = null;
        for ($r = 1; $r <= $highestRow; $r++) {
            $col = 'A';
            while (true) {
                $val = mb_strtolower(trim((string) $sheet->getCell("{$col}{$r}")->getValue()));
                if (in_array($val, $signatureKeys, true)) {
                    $headerRow = $r;
                    break 2;
                }
                if ($col === $highestCol) {
                    break;
                }
                $col++;
            }
        }
        if ($headerRow === null) {
            return [];
        }

        $headerMap = [];
        $col = 'A';
        while (true) {
            $val = trim((string) $sheet->getCell("{$col}{$headerRow}")->getValue());
            if ($val !== '') {
                $headerMap[mb_strtolower($val)] = $col;
            }
            if ($col === $highestCol) {
                break;
            }
            $col++;
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $first = trim((string) $sheet->getCell("A{$r}")->getValue());
            if (Str::startsWith(mb_strtoupper($first), 'END OF REPORT')) {
                break;
            }
            $row = [];
            foreach ($columnAliases as $key => $candidates) {
                $row[$key] = $this->cell($sheet, $headerMap, $candidates, $r);
            }
            if (collect($row)->filter(fn($v) => $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function cell($sheet, array $headerMap, array $candidates, int $row): string
    {
        foreach ($candidates as $candidate) {
            $key = mb_strtolower($candidate);
            if (isset($headerMap[$key])) {
                $v = $sheet->getCell("{$headerMap[$key]}{$row}")->getValue();
                return trim((string) ($v ?? ''));
            }
        }
        return '';
    }

    /**
     * Normalize raw cell values into the shape the controllers expect.
     * Resolves type/governorate/city names to IDs.
     */
    private function parseRow(array $raw, $facilityTypes, $governorates, $cities): array
    {
        $typeId = $this->lookupId($raw['facility_type'] ?? '', $facilityTypes);
        $govId = $this->lookupId($raw['governorate'] ?? '', $governorates);
        $cityId = $this->lookupId($raw['city'] ?? '', $cities);

        return [
            'name' => $raw['name'],
            'name_ar' => $raw['name_ar'] !== '' ? $raw['name_ar'] : ($raw['name'] ?: null),
            'slug' => $raw['slug'] !== '' ? Str::slug($raw['slug']) : null,
            'facility_type_id' => $typeId,
            'facility_type_input' => $raw['facility_type'] ?? '',
            'governorate_id' => $govId,
            'governorate_input' => $raw['governorate'] ?? '',
            'city_id' => $cityId,
            'city_input' => $raw['city'] ?? '',
            'latitude' => $raw['latitude'] !== '' ? (float) $raw['latitude'] : null,
            'longitude' => $raw['longitude'] !== '' ? (float) $raw['longitude'] : null,
        ];
    }

    private function lookupId(string $input, $index): ?int
    {
        $key = mb_strtolower(trim($input));
        if ($key === '') {
            return null;
        }
        return $index->get($key)?->id;
    }

    private function indexByTranslatedName($collection)
    {
        return $collection
            ->keyBy(fn($m) => mb_strtolower(trim((string) $m->getTranslation('name', 'en'))))
            ->merge($collection->keyBy(fn($m) => mb_strtolower(trim((string) $m->getTranslation('name', 'ar')))));
    }

    /**
     * Per-row validation. Mirrors StoreFacilityRequest essentials but lighter,
     * since the commit endpoint runs the strict rules again before persisting.
     */
    private function validateRow(array $parsed): array
    {
        $errors = [];
        if (empty($parsed['name'])) {
            $errors['name'] = 'Name is required.';
        }
        if ($parsed['facility_type_id'] === null) {
            $errors['facility_type'] = !empty($parsed['facility_type_input'])
                ? "Facility type \"{$parsed['facility_type_input']}\" not found."
                : 'Facility type is required.';
        }
        if ($parsed['governorate_id'] === null) {
            $errors['governorate'] = !empty($parsed['governorate_input'])
                ? "Governorate \"{$parsed['governorate_input']}\" not found."
                : 'Governorate is required.';
        }
        if (!empty($parsed['city_input']) && $parsed['city_id'] === null) {
            $errors['city'] = "City \"{$parsed['city_input']}\" not found.";
        }
        if ($parsed['latitude'] !== null && ($parsed['latitude'] < -90 || $parsed['latitude'] > 90)) {
            $errors['latitude'] = 'Latitude must be between -90 and 90.';
        }
        if ($parsed['longitude'] !== null && ($parsed['longitude'] < -180 || $parsed['longitude'] > 180)) {
            $errors['longitude'] = 'Longitude must be between -180 and 180.';
        }
        return $errors;
    }

    /**
     * Build a field-level diff between an existing facility and the parsed row.
     */
    private function buildDiff(Facility $facility, array $parsed): array
    {
        $oldType = $facility->facilityType?->getTranslation('name', app()->getLocale())
            ?: ($facility->facilityType?->getTranslation('name', 'en') ?: '');
        $oldName = $facility->getTranslation('name', app()->getLocale())
            ?: ($facility->getTranslation('name', 'en') ?: '');

        $fields = [
            ['field' => 'name', 'label' => 'Name', 'old' => $oldName, 'new' => $parsed['name']],
            ['field' => 'facility_type', 'label' => 'Facility type', 'old' => $oldType, 'new' => $parsed['facility_type_input']],
            ['field' => 'latitude', 'label' => 'Latitude', 'old' => $facility->latitude, 'new' => $parsed['latitude']],
            ['field' => 'longitude', 'label' => 'Longitude', 'old' => $facility->longitude, 'new' => $parsed['longitude']],
        ];
        return array_map(function ($f) {
            $oldNorm = (string) ($f['old'] ?? '');
            $newNorm = (string) ($f['new'] ?? '');
            $f['changed'] = $oldNorm !== $newNorm;
            return $f;
        }, $fields);
    }
}
