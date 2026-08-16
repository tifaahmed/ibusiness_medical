<?php

namespace App\Http\Controllers\Admin\Sales\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminSalesImportPreviewController extends BaseController
{
    /**
     * Parse an uploaded CSV/XLSX into rows shaped like the sales export, and
     * report for each row whether it already exists in the database (matched by
     * id). Nothing is written to the database — the frontend shows this preview
     * so the user can edit the names or drop rows before confirming the import.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());

        $rows = $this->parseSpreadsheet($path, $extension);

        $existingIds = [];
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $existingIds[(int) $row['id']] = true;
            }
        }
        $foundIds = [];
        if (!empty($existingIds)) {
            $foundIds = Sales::query()
                ->whereIn('id', array_keys($existingIds))
                ->pluck('id', 'id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $validUserIds = [];
        $preview = [];
        foreach ($rows as $i => $row) {
            $id = $row['id'] !== '' ? (int) $row['id'] : null;
            $errors = $this->validateRow($row, $id, $foundIds);

            if (!empty($row['created_by']) && !array_key_exists((int) $row['created_by'], $validUserIds)) {
                $validUserIds[(int) $row['created_by']] = User::whereKey((int) $row['created_by'])->exists();
            }

            $preview[] = [
                'index' => $i,
                'id' => $id,
                'name_ar' => $row['name_ar'],
                'name_en' => $row['name_en'],
                'image_url' => $row['image_url'],
                'created_by' => $row['created_by'] !== '' ? (int) $row['created_by'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'status' => ($id !== null && isset($foundIds[$id])) ? 'exists' : 'new',
                'errors' => $errors,
                'user_exists' => !empty($row['created_by']) && ($validUserIds[(int) $row['created_by']] ?? false),
            ];
        }

        return response()->json([
            'rows' => $preview,
            'total_rows' => count($preview),
            'existing_count' => collect($preview)->where('status', 'exists')->count(),
            'new_count' => collect($preview)->where('status', 'new')->count(),
        ]);
    }

    /**
     * Read the spreadsheet and return rows as associative arrays keyed by column
     * name. The header row is located by finding a row that contains the "#"
     * and "Name" signature — the same shape the export/template emit.
     *
     * @return array<int, array{id: string, name_ar: string, name_en: string, image_url: string, created_by: string, created_at: string, updated_at: string}>
     */
    private function parseSpreadsheet(string $path, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('Sales') ?? $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $headerRow = null;
        for ($r = 1; $r <= $highestRow; $r++) {
            $col = 'A';
            while (true) {
                $val = mb_strtolower(trim((string) $sheet->getCell("{$col}{$r}")->getValue()));
                if ($val === '#') {
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
            $spreadsheet->disconnectWorksheets();
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

        $aliases = [
            'id' => ['#'],
            'name_en' => ['name', 'name (en)', 'english name'],
            'name_ar' => ['name (ar)', 'name_ar', 'arabic name'],
            'image_url' => ['image url', 'image_url', 'image'],
            'created_by' => ['created by', 'created_by', 'creator'],
            'created_at' => ['created at', 'created_at'],
            'updated_at' => ['updated at', 'updated_at'],
        ];

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $first = trim((string) $sheet->getCell("A{$r}")->getValue());
            if (Str::startsWith(mb_strtoupper($first), 'END OF REPORT')) {
                break;
            }
            $row = [];
            foreach ($aliases as $key => $candidates) {
                $row[$key] = $this->cell($sheet, $headerMap, $candidates, $r);
            }
            if ($row['name_ar'] === '' && $row['name_en'] === '') {
                continue;
            }
            $rows[] = $row;
        }

        $spreadsheet->disconnectWorksheets();
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
     * Per-row validation shown in the preview. The commit endpoint re-validates
     * and skips rows that are still invalid.
     */
    private function validateRow(array $row, ?int $id, array $foundIds): array
    {
        $errors = [];
        if ($row['name_ar'] === '' && $row['name_en'] === '') {
            $errors['name'] = 'At least one name (Name or Name (AR)) is required.';
        }
        if ($row['name_en'] !== '' && mb_strlen($row['name_en']) > 255) {
            $errors['name_en'] = 'Name must be 255 characters or less.';
        }
        if ($row['name_ar'] !== '' && mb_strlen($row['name_ar']) > 255) {
            $errors['name_ar'] = 'Name (AR) must be 255 characters or less.';
        }
        if ($id !== null && $id <= 0) {
            $errors['id'] = 'The id must be a positive integer.';
        }
        return $errors;
    }
}
