<?php

namespace App\Http\Controllers\Admin\MemberPayment\Import;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminMemberPaymentImportPreviewController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $rows = $this->parseSpreadsheet(
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalExtension()
        );

        $rows = array_values(array_filter($rows, fn($row) => !empty($row['membership_number']) || !empty($row['email'])));

        $partners = Partner::all()->keyBy(fn($p) => mb_strtolower(trim((string) $p->title)));

        $preview = [];
        foreach ($rows as $i => $raw) {
            $parsed = $this->parseRow($raw, $partners);
            $errors = $this->validateRow($parsed);

            $match = null;
            if (!empty($parsed['membership_number'])) {
                $match = Membership::with('user', 'partner')
                    ->where('membership_number', $parsed['membership_number'])
                    ->first();
            }
            if (!$match && !empty($parsed['email'])) {
                $match = Membership::with('user', 'partner')
                    ->whereHas('user', fn($q) => $q->where('email', $parsed['email']))
                    ->first();
            }

            $latestPaymentToDate = null;
            $registrationDate = null;
            if ($match) {
                $registrationDate = $match->registration_date?->toDateString();
                $latestPayment = $match->memberPayments()
                    ->orderBy('to_date', 'desc')
                    ->first();
                if ($latestPayment) {
                    $latestPaymentToDate = $latestPayment->to_date?->toDateString();
                }
            }

            $status = !empty($errors) ? 'error' : ($match ? 'matched' : 'unmatched');

            $preview[] = [
                'index' => $i,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
                'status' => $status,
                'match' => $match ? [
                    'membership_id' => $match->id,
                    'user_id' => $match->user?->id,
                    'membership_number' => $match->membership_number,
                    'name' => $match->user?->name,
                    'registration_date' => $registrationDate,
                    'latest_payment_to_date' => $latestPaymentToDate,
                ] : null,
            ];
        }

        return response()->json([
            'rows' => $preview,
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

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $this->extractRowsFromSheet(
            $sheet,
            ['name'],
            [
                'name' => ['name'],
                'email' => ['email'],
                'phone' => ['phone'],
                'membership_number' => ['membership #', 'membership_number', 'membership'],
                'partner' => ['partner'],
                'amount_paid' => ['amount paid', 'amount_paid', 'amount'],
                'type' => ['type'],
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
                if ($col === $highestCol) break;
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
            if ($col === $highestCol) break;
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

    private function parseRow(array $raw, $partners): array
    {
        $type = mb_strtolower(trim($raw['type'] ?? ''));
        if (!in_array($type, ['commission', 'profit', 'free', ''], true)) {
            $type = '';
        }

        $partnerId = null;
        if (!empty($raw['partner'])) {
            $key = mb_strtolower(trim($raw['partner']));
            $partnerId = $partners->get($key)?->id;
        }

        return [
            'name' => $raw['name'],
            'email' => $raw['email'] !== '' ? mb_strtolower($raw['email']) : null,
            'phone' => $raw['phone'] !== '' ? $raw['phone'] : null,
            'membership_number' => $raw['membership_number'],
            'partner_name' => $raw['partner'],
            'partner_id' => $partnerId,
            'amount' => $this->parseAmount($raw['amount_paid']),
            'type' => $type,
        ];
    }

    private function parseAmount(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $clean = str_replace([',', ' '], '', $raw);
        if (!is_numeric($clean)) return null;
        $val = (float) $clean;
        return $val >= 0 ? $val : null;
    }

    private function validateRow(array $parsed): array
    {
        $errors = [];
        if (empty($parsed['membership_number']) && empty($parsed['email'])) {
            $errors['identification'] = 'Membership number or email is required.';
        }
        if ($parsed['amount'] === null || $parsed['amount'] === '') {
            $errors['amount'] = 'Amount is required and must be a non-negative number.';
        }
        if (!empty($parsed['type']) && !in_array($parsed['type'], ['commission', 'profit', 'free'], true)) {
            $errors['type'] = 'Type must be Commission, Profit, Free, or left empty (defaults to Commission).';
        }
        return $errors;
    }
}
