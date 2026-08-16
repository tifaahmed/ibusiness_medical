<?php

namespace App\Http\Controllers\Admin\Company\Import;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Concerns\ExportsCompanyColumns;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminCompanyImportPreviewController extends BaseController
{
    use CreatorScoped;
    use ExportsCompanyColumns;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * Parse an uploaded CSV/XLSX, match each row to an existing company (by id,
     * then slug, then name in any locale), validate it and compute a per-field
     * diff for rows that would be updated. Nothing is written to the database.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        try {
            $rows = $this->parseSpreadsheet(
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalExtension()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $preview = [];
        foreach ($rows as $i => $raw) {
            $parsed = $this->parseRow($raw);
            $errors = $this->validateRow($parsed);

            $match = $errors['id'] ?? null ? null : $this->findMatch($parsed);

            $diff = $match ? $this->buildDiff($match, $parsed) : [];
            $hasChanges = collect($diff)->contains(fn ($d) => $d['changed']);

            $status = !empty($errors) ? 'error' : ($match ? ($hasChanges ? 'update' : 'unchanged') : 'new');

            $preview[] = [
                'index' => $i,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
                'status' => $status,
                'match' => $match ? [
                    'company_id' => $match->id,
                    'slug' => $match->slug,
                    'name' => $match->getTranslation('name', app()->getLocale()) ?: $match->getTranslation('name', 'en'),
                ] : null,
                'diff' => $diff,
            ];
        }

        return response()->json([
            'rows' => $preview,
            'columns' => $this->companyColumnDefinitions(),
        ]);
    }

    /**
     * Read the spreadsheet and return rows as associative arrays keyed by the
     * canonical column keys. Works with the export/template output as well as
     * hand-made files: it locates the header row by looking for at least two
     * known column headers.
     */
    private function parseSpreadsheet(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader();
            $reader->setInputEncoding('UTF-8');
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $this->extractRowsFromSheet($sheet);

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function extractRowsFromSheet($sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $aliases = $this->companyColumnAliases();
        $allAliases = collect($aliases)->flatten()->map(fn ($a) => $this->normalizeHeader($a))->all();

        // Locate the header row: the row containing at least two known headers.
        $headerRow = null;
        for ($r = 1; $r <= $highestRow; $r++) {
            $found = 0;
            $col = 'A';
            while (true) {
                $val = $this->normalizeHeader((string) $sheet->getCell("{$col}{$r}")->getValue());
                if ($val !== '' && in_array($val, $allAliases, true)) {
                    $found++;
                }
                if ($col === $highestCol) {
                    break;
                }
                $col++;
            }
            if ($found >= 2) {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === null) {
            throw new \RuntimeException(
                'No header row found. The file needs a row with at least two known column names ' .
                '(e.g. "ID", "Name (English)", "Name (Arabic)") — export a companies file to see the expected format.'
            );
        }

        $headerMap = [];
        $col = 'A';
        while (true) {
            $val = $this->normalizeHeader((string) $sheet->getCell("{$col}{$headerRow}")->getValue());
            if ($val !== '') {
                $headerMap[$val] = $col;
            }
            if ($col === $highestCol) {
                break;
            }
            $col++;
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $row = [];
            foreach ($aliases as $key => $candidates) {
                $row[$key] = $this->cell($sheet, $headerMap, $candidates, $r);
            }
            if (collect($row)->filter(fn ($v) => $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function cell($sheet, array $headerMap, array $candidates, int $row): string
    {
        foreach ($candidates as $candidate) {
            $key = $this->normalizeHeader($candidate);
            if (isset($headerMap[$key])) {
                $v = $sheet->getCell("{$headerMap[$key]}{$row}")->getValue();
                return trim((string) ($v ?? ''));
            }
        }
        return '';
    }

    private function parseRow(array $raw): array
    {
        return [
            'id'               => trim((string) ($raw['id'] ?? '')),
            'name_en'          => trim((string) ($raw['name_en'] ?? '')),
            'name_ar'          => trim((string) ($raw['name_ar'] ?? '')),
            'slug'             => trim((string) ($raw['slug'] ?? '')),
            'created_by_email' => trim((string) ($raw['created_by_email'] ?? '')),
            'created_at'       => trim((string) ($raw['created_at'] ?? '')),
            'updated_at'       => trim((string) ($raw['updated_at'] ?? '')),
        ];
    }

    private function validateRow(array $parsed): array
    {
        $errors = [];
        if (empty($parsed['name_en']) && empty($parsed['name_ar'])) {
            $errors['name'] = 'At least one company name (English or Arabic) is required.';
        }
        if (!empty($parsed['id']) && !ctype_digit($parsed['id'])) {
            $errors['id'] = 'ID must be a whole number.';
        }
        if (!empty($parsed['created_at']) && strtotime($parsed['created_at']) === false) {
            $errors['created_at'] = 'Created At must be a valid date (YYYY-MM-DD HH:MM:SS).';
        }
        if (!empty($parsed['updated_at']) && strtotime($parsed['updated_at']) === false) {
            $errors['updated_at'] = 'Updated At must be a valid date (YYYY-MM-DD HH:MM:SS).';
        }
        return $errors;
    }

    private function findMatch(array $parsed): ?Company
    {
        $query = fn () => Company::query()->tap(fn ($q) => $this->applyCreatorScope($q));

        if (!empty($parsed['id'])) {
            $match = $query()->where('id', (int) $parsed['id'])->first();
            if ($match) {
                return $match;
            }
        }

        if (!empty($parsed['slug'])) {
            $match = $query()->where('slug', $parsed['slug'])->first();
            if ($match) {
                return $match;
            }
        }

        if (!empty($parsed['name_en'])) {
            $needle = mb_strtolower(trim($parsed['name_en']));
            $match = $query()
                ->where('name->en', $parsed['name_en'])
                ->orWhere('name->ar', $parsed['name_en'])
                ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$needle])
                ->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function buildDiff(Company $company, array $parsed): array
    {
        $fields = [
            ['field' => 'name_en', 'label' => __('admin.company_export.col_name_en'), 'old' => $company->getTranslation('name', 'en'), 'new' => $parsed['name_en']],
            ['field' => 'name_ar', 'label' => __('admin.company_export.col_name_ar'), 'old' => $company->getTranslation('name', 'ar'), 'new' => $parsed['name_ar']],
            ['field' => 'slug', 'label' => __('admin.company_export.col_slug'), 'old' => $company->slug, 'new' => $parsed['slug']],
        ];
        return array_map(function ($f) {
            $f['changed'] = (string) ($f['old'] ?? '') !== (string) ($f['new'] ?? '');
            return $f;
        }, $fields);
    }
}
