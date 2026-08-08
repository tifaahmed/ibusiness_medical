<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminFacilityBranchImportPreviewController extends BaseController
{
    /**
     * Parse an uploaded CSV/XLSX, match each row to an existing branch
     * (by facility + branch name), validate, and compute a per-field diff
     * for rows that would be updated. Nothing is written to the database.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $rows = $this->parseSpreadsheet(
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalExtension()
        );

        $facilities = $this->indexByTranslatedName(Facility::all());
        $facilitiesBySlug = Facility::all()->keyBy(fn($f) => mb_strtolower(trim((string) $f->slug)));
        $governorates = $this->indexByTranslatedName(Governorate::all());
        $cities = $this->indexByTranslatedName(City::all());

        $preview = [];
        foreach ($rows as $i => $raw) {
            $parsed = $this->parseRow($raw, $facilities, $facilitiesBySlug, $governorates, $cities);
            $errors = $this->validateRow($parsed);

            $match = null;
            if ($parsed['facility_id'] !== null && !empty($parsed['name'])) {
                $needle = mb_strtolower(trim($parsed['name']));
                $match = FacilityBranch::with(['facility', 'governorate', 'city'])
                    ->where('facility_id', $parsed['facility_id'])
                    ->where(function ($q) use ($parsed, $needle) {
                        $q->where('name->en', $parsed['name'])
                          ->orWhere('name->ar', $parsed['name'])
                          ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$needle])
                          ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.ar"))) = ?', [$needle]);
                    })
                    ->first();
            }

            $diff = $match ? $this->buildDiff($match, $parsed) : [];
            $hasChanges = collect($diff)->contains(fn($d) => $d['changed']);

            $status = !empty($errors) ? 'error' : ($match ? ($hasChanges ? 'update' : 'unchanged') : 'new');

            $preview[] = [
                'index' => $i,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
                'status' => $status,
                'match' => $match ? [
                    'branch_id' => $match->id,
                    'name' => $match->getTranslation('name', app()->getLocale()) ?: $match->getTranslation('name', 'en'),
                    'facility_name' => $match->facility?->getTranslation('name', app()->getLocale())
                        ?: $match->facility?->getTranslation('name', 'en'),
                ] : null,
                'diff' => $diff,
            ];
        }

        return response()->json([
            'rows' => $preview,
            'facilityOptions' => Facility::orderBy('id')->get()->map(fn($f) => [
                'value' => $f->id,
                'label' => $f->getTranslation('name', app()->getLocale()) ?: $f->getTranslation('name', 'en'),
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

        $sheet = $spreadsheet->getSheetByName('Branches') ?? $spreadsheet->getActiveSheet();
        $rows = $this->extractRowsFromSheet(
            $sheet,
            ['branch name', 'name'],
            [
                'facility_name' => ['facility name', 'facility'],
                'facility_slug' => ['facility slug', 'slug'],
                'name' => ['branch name', 'name'],
                'name_ar' => ['branch name (ar)', 'name_ar'],
                'address' => ['address'],
                'address_ar' => ['address (ar)', 'address_ar'],
                'phone' => ['phone', 'phones'],
                'governorate' => ['governorate'],
                'city' => ['city'],
                'latitude' => ['latitude', 'lat'],
                'longitude' => ['longitude', 'lng', 'long'],
            ]
        );

        $spreadsheet->disconnectWorksheets();
        return $rows;
    }

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

    private function parseRow(array $raw, $facilities, $facilitiesBySlug, $governorates, $cities): array
    {
        $facilityId = null;
        $facilityInput = $raw['facility_name'] ?? '';
        if (!empty($raw['facility_slug'])) {
            $slugKey = mb_strtolower(trim($raw['facility_slug']));
            $facilityId = $facilitiesBySlug->get($slugKey)?->id;
            if (!$facilityInput && $facilityId) {
                $f = $facilitiesBySlug->get($slugKey);
                $facilityInput = $f?->getTranslation('name', 'en') ?: '';
            }
        }
        if ($facilityId === null) {
            $facilityId = $this->lookupId($facilityInput, $facilities);
        }

        $govId = $this->lookupId($raw['governorate'] ?? '', $governorates);
        $cityId = $this->lookupId($raw['city'] ?? '', $cities);

        return [
            'facility_id' => $facilityId,
            'facility_input' => $facilityInput,
            'name' => $raw['name'],
            'name_ar' => $raw['name_ar'] !== '' ? $raw['name_ar'] : ($raw['name'] ?: null),
            'address' => $raw['address'] !== '' ? $raw['address'] : null,
            'address_ar' => $raw['address_ar'] !== '' ? $raw['address_ar'] : ($raw['address'] ?: null),
            'phone' => $raw['phone'] !== '' ? $raw['phone'] : null,
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

    private function validateRow(array $parsed): array
    {
        $errors = [];
        if (empty($parsed['name'])) {
            $errors['name'] = 'Branch name is required.';
        }
        if ($parsed['facility_id'] === null) {
            $errors['facility'] = !empty($parsed['facility_input'])
                ? "Facility \"{$parsed['facility_input']}\" not found."
                : 'Facility is required.';
        }
        if (!empty($parsed['governorate_input']) && $parsed['governorate_id'] === null) {
            $errors['governorate'] = "Governorate \"{$parsed['governorate_input']}\" not found.";
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

    private function buildDiff(FacilityBranch $branch, array $parsed): array
    {
        $oldGov = $branch->governorate?->getTranslation('name', app()->getLocale())
            ?: ($branch->governorate?->getTranslation('name', 'en') ?: '');
        $oldCity = $branch->city?->getTranslation('name', app()->getLocale())
            ?: ($branch->city?->getTranslation('name', 'en') ?: '');
        $oldName = $branch->getTranslation('name', app()->getLocale())
            ?: ($branch->getTranslation('name', 'en') ?: '');
        $oldAddress = $branch->getTranslation('address', app()->getLocale())
            ?: ($branch->getTranslation('address', 'en') ?: '');
        $oldPhone = is_array($branch->phone) ? implode(', ', $branch->phone) : (string) ($branch->phone ?? '');

        $fields = [
            ['field' => 'name', 'label' => 'Name', 'old' => $oldName, 'new' => $parsed['name']],
            ['field' => 'address', 'label' => 'Address', 'old' => $oldAddress, 'new' => $parsed['address']],
            ['field' => 'phone', 'label' => 'Phone', 'old' => $oldPhone, 'new' => $parsed['phone']],
            ['field' => 'governorate', 'label' => 'Governorate', 'old' => $oldGov, 'new' => $parsed['governorate_input']],
            ['field' => 'city', 'label' => 'City', 'old' => $oldCity, 'new' => $parsed['city_input']],
            ['field' => 'latitude', 'label' => 'Latitude', 'old' => $branch->latitude, 'new' => $parsed['latitude']],
            ['field' => 'longitude', 'label' => 'Longitude', 'old' => $branch->longitude, 'new' => $parsed['longitude']],
        ];
        return array_map(function ($f) {
            $oldNorm = (string) ($f['old'] ?? '');
            $newNorm = (string) ($f['new'] ?? '');
            $f['changed'] = $oldNorm !== $newNorm;
            return $f;
        }, $fields);
    }
}
