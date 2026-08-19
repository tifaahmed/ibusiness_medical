<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class AdminUserMembershipImportPreviewController extends BaseController
{
    /**
     * Parse an uploaded CSV/XLSX, match each row to an existing member
     * (by membership number, then email), validate, and compute a per-field
     * diff for rows that would be updated. Nothing is written to the database.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        ['members' => $rows, 'families' => $familiesByNumber, 'payments' => $paymentsByNumber] = $this->parseSpreadsheet(
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalExtension()
        );

        // Pre-fetch lookup tables once.
        $companies = Company::all()->keyBy(fn ($c) => mb_strtolower(trim((string) $c->getTranslation('name', 'en'))))
            ->merge(Company::all()->keyBy(fn ($c) => mb_strtolower(trim((string) $c->getTranslation('name', 'ar')))))
            ->merge(Company::all()->keyBy(fn ($c) => (string) $c->id));
        $partners = Partner::all()->keyBy(fn ($p) => mb_strtolower(trim((string) $p->title)))
            ->merge(Partner::all()->keyBy(fn ($p) => (string) $p->id));

        $preview = [];
        foreach ($rows as $i => $raw) {
            $parsed = $this->parseRow($raw, $companies, $partners);
            $errors = $this->validateRow($parsed);

            $match = null;
            $matchedUser = null;
            if (! empty($parsed['membership_number'])) {
                $match = Membership::with('user', 'company', 'partner')
                    ->where('membership_number', $parsed['membership_number'])
                    ->first();
            }
            if (! $match && ! empty($parsed['email'])) {
                $matchedUser = User::with(['memberships' => fn ($q) => $q->orderBy('is_active', 'desc')->orderBy('created_at', 'desc')->with(['company', 'partner'])])
                    ->where('email', $parsed['email'])
                    ->first();
                $match = $matchedUser?->memberships->first();
            } elseif ($match) {
                $matchedUser = $match->user;
            }

            $diff = $match ? $this->buildDiff($matchedUser, $match, $parsed) : [];
            $hasChanges = collect($diff)->contains(fn ($d) => $d['changed']);

            $status = ! empty($errors) ? 'error' : ($match ? ($hasChanges ? 'update' : 'unchanged') : 'new');

            // Prefer the live DB avatar over whatever was in the file (older
            // exports didn't carry an Avatar URL column at all).
            if ($matchedUser) {
                $parsed['avatar_url'] = get_image_url($matchedUser, 'avatar');
            }

            // Pick up family members from the second sheet, keyed by membership #.
            $families = $familiesByNumber[$parsed['membership_number']] ?? [];

            // Pick up payments from the third sheet, keyed by membership #.
            $payments = $paymentsByNumber[$parsed['membership_number']] ?? [];

            // Same fallback for family photos: if we matched the membership,
            // use each existing family member's live photo URL when the row
            // doesn't carry one.
            if ($match && $match->relationLoaded('familyMembers') === false) {
                $match->load('familyMembers');
            }
            if (! empty($families) && $match) {
                $existingByName = $match->familyMembers->keyBy(fn ($f) => mb_strtolower(trim((string) $f->name)));
                foreach ($families as $fi => $f) {
                    if (empty($f['photo_url'])) {
                        $key = mb_strtolower(trim((string) $f['name']));
                        $existing = $existingByName->get($key);
                        if ($existing) {
                            $families[$fi]['photo_url'] = get_image_url($existing, 'photo');
                        }
                    }
                }
            }

            // For existing memberships, also load existing payments from DB
            // so the frontend can show them when the import has no payment rows.
            if ($match) {
                if (! $match->relationLoaded('memberPayments')) {
                    $match->load('memberPayments');
                }
                if (empty($payments)) {
                    $payments = $match->memberPayments->map(fn ($p) => [
                        'amount' => (string) $p->amount,
                        'months_paid' => (string) $p->months_paid,
                        'from_date' => $p->from_date?->format('Y-m-d'),
                        'to_date' => $p->to_date?->format('Y-m-d'),
                        'notes' => $p->notes ?? '',
                        'type' => $p->type ?? 'commission',
                        'existing_id' => $p->id,
                    ])->toArray();
                }
            }

            $preview[] = [
                'index' => $i,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
                'status' => $status,
                'match' => $match ? [
                    'membership_id' => $match->id,
                    'user_id' => $matchedUser?->id,
                    'user_slug' => $matchedUser?->slug,
                    'membership_number' => $match->membership_number,
                    'name' => $matchedUser?->name,
                    'email' => $matchedUser?->email,
                ] : null,
                'diff' => $diff,
                'families' => $families,
                'payments' => $payments,
            ];
        }

        return response()->json([
            'rows' => $preview,
            'companyOptions' => Company::orderBy('id')->get()->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->getTranslation('name', app()->getLocale())
                    ?: ($c->getTranslation('name', 'ar') ?: ($c->getTranslation('name', 'en') ?: "#{$c->id}")),
            ])->values(),
            'partnerOptions' => Partner::orderBy('title')->get()->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->title,
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
            $reader = new CsvReader;
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $membersSheet = $spreadsheet->getSheetByName('Members') ?? $spreadsheet->getActiveSheet();
        $members = $this->extractRowsFromSheet(
            $membersSheet,
            ['name'],
            [
                'name' => ['name'],
                'email' => ['email'],
                'phone' => ['phone'],
                'membership_number' => ['membership #', 'membership_number', 'membership'],
                'status' => ['status'],
                'visibility' => ['visibility'],
                'job_title' => ['job title', 'job_title'],
                'company' => ['company'],
                'company_id_raw' => ['company id', 'company_id'],
                'partner' => ['partner'],
                'partner_id_raw' => ['partner id', 'partner_id'],
                'registration_date' => ['registration date', 'registration_date'],
                'expiration_date' => ['expiration date', 'expiration_date'],
                'avatar_url' => ['avatar url', 'avatar_url'],
            ]
        );

        $families = [];
        $familySheet = $spreadsheet->getSheetByName('Family Members');
        if ($familySheet) {
            $familyRows = $this->extractRowsFromSheet(
                $familySheet,
                ['family member name', 'name'],
                [
                    'member_name' => ['member name'],
                    'membership_number' => ['membership #', 'membership_number'],
                    'name' => ['family member name', 'name'],
                    'relationship' => ['relationship'],
                    'date_of_birth' => ['date of birth', 'date_of_birth', 'dob'],
                    'phone' => ['phone'],
                    'email' => ['email'],
                    'status' => ['status'],
                    'photo_url' => ['photo url', 'photo_url'],
                ]
            );
            foreach ($familyRows as $f) {
                $key = $f['membership_number'];
                if ($key === '') {
                    continue;
                }
                $families[$key][] = [
                    'name' => $f['name'],
                    'relationship' => mb_strtolower($f['relationship']),
                    'date_of_birth' => $this->parseDate($f['date_of_birth']),
                    'phone' => $f['phone'] !== '' ? $f['phone'] : null,
                    'email' => $f['email'] !== '' ? mb_strtolower($f['email']) : null,
                    'is_active' => $this->parseBool($f['status'], ['active' => true, 'inactive' => false], true),
                    'photo_url' => $f['photo_url'] !== '' ? $f['photo_url'] : null,
                ];
            }
        }

        $payments = [];
        $paymentsSheet = $spreadsheet->getSheetByName('Payments');
        if ($paymentsSheet) {
            $paymentRows = $this->extractRowsFromSheet(
                $paymentsSheet,
                ['membership #', 'membership_number'],
                [
                    'membership_number' => ['membership #', 'membership_number'],
                    'amount' => ['amount'],
                    'months_paid' => ['months paid', 'months_paid'],
                    'from_date' => ['from date', 'from_date'],
                    'to_date' => ['to date', 'to_date'],
                    'notes' => ['notes'],
                    'type' => ['type'],
                ]
            );
            foreach ($paymentRows as $p) {
                $key = $p['membership_number'];
                if ($key === '') {
                    continue;
                }
                $payments[$key][] = [
                    'amount' => $p['amount'],
                    'months_paid' => $p['months_paid'],
                    'from_date' => $this->parseDate($p['from_date']),
                    'to_date' => $this->parseDate($p['to_date']),
                    'notes' => $p['notes'] !== '' ? $p['notes'] : null,
                    'type' => $p['type'] !== '' ? $p['type'] : 'commission',
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['members' => $members, 'families' => $families, 'payments' => $payments];
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
     * Resolves company/partner names to IDs and parses dates.
     */
    private function parseRow(array $raw, $companies, $partners): array
    {
        $companyId = null;
        if (! empty($raw['company_id_raw']) && is_numeric(trim($raw['company_id_raw']))) {
            $companyId = $companies->get(trim($raw['company_id_raw']))?->id;
        }
        if (! $companyId && ! empty($raw['company'])) {
            $key = mb_strtolower(trim($raw['company']));
            $companyId = $companies->get($key)?->id;
        }
        $partnerId = null;
        if (! empty($raw['partner_id_raw']) && is_numeric(trim($raw['partner_id_raw']))) {
            $partnerId = $partners->get(trim($raw['partner_id_raw']))?->id;
        }
        if (! $partnerId && ! empty($raw['partner'])) {
            $key = mb_strtolower(trim($raw['partner']));
            $partnerId = $partners->get($key)?->id;
        }

        return [
            'name' => $raw['name'],
            'email' => $raw['email'] !== '' ? mb_strtolower($raw['email']) : null,
            'phone' => $raw['phone'] !== '' ? $raw['phone'] : null,
            'membership_number' => $raw['membership_number'],
            'is_active' => $this->parseBool($raw['status'], ['active' => true, 'inactive' => false], true),
            'is_visible' => $this->parseBool($raw['visibility'], ['visible' => true, 'hidden' => false], true),
            'job_title' => $raw['job_title'] !== '' ? $raw['job_title'] : null,
            'company_id' => $companyId,
            'company_name_input' => $raw['company'],
            'partner_id' => $partnerId,
            'partner_name_input' => $raw['partner'],
            'registration_date' => $this->parseDate($raw['registration_date']),
            'expiration_date' => $this->parseDate($raw['expiration_date']),
            'avatar_url' => $raw['avatar_url'] ?? null,
        ];
    }

    private function parseBool(string $raw, array $map, bool $default): bool
    {
        $key = mb_strtolower(trim($raw));

        return $map[$key] ?? $default;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        foreach (['d M Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'm/d/Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $raw);
                if ($d) {
                    return $d->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try next
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Per-row validation. Mirrors UpdateMembershipRequest essentials but lighter,
     * since the commit endpoint runs the strict rules again before persisting.
     */
    private function validateRow(array $parsed): array
    {
        $errors = [];
        if (empty($parsed['name'])) {
            $errors['name'] = 'Name is required.';
        }
        if (empty($parsed['membership_number'])) {
            $errors['membership_number'] = 'Membership number is required.';
        }
        if (empty($parsed['expiration_date'])) {
            $errors['expiration_date'] = 'Expiration date is required.';
        }
        if (! empty($parsed['email']) && ! filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        }
        if ($parsed['registration_date'] && $parsed['expiration_date']
            && strtotime($parsed['expiration_date']) <= strtotime($parsed['registration_date'])) {
            $errors['expiration_date'] = 'Expiration must be after registration.';
        }
        if (! empty($parsed['company_name_input']) && $parsed['company_id'] === null) {
            $errors['company'] = "Company \"{$parsed['company_name_input']}\" not found.";
        }
        if (! empty($parsed['partner_name_input']) && $parsed['partner_id'] === null) {
            $errors['partner'] = "Partner \"{$parsed['partner_name_input']}\" not found.";
        }

        return $errors;
    }

    /**
     * Build a field-level diff between an existing member/membership
     * and the parsed import row. Each entry: field, label, old, new, changed.
     */
    private function buildDiff(?User $user, ?Membership $membership, array $parsed): array
    {
        $oldJobTitle = $membership?->getTranslation('job_title', app()->getLocale())
            ?: ($membership?->getTranslation('job_title', 'ar')
            ?: ($membership?->getTranslation('job_title', 'en') ?: ''));
        $oldCompany = $membership?->company?->getTranslation('name', app()->getLocale())
            ?: ($membership?->company?->getTranslation('name', 'en') ?: '');
        $oldPartner = $membership?->partner?->title ?? '';

        $fields = [
            ['field' => 'name', 'label' => 'Name', 'old' => $user?->name, 'new' => $parsed['name']],
            ['field' => 'email', 'label' => 'Email', 'old' => $user?->email, 'new' => $parsed['email']],
            ['field' => 'phone', 'label' => 'Phone', 'old' => $user?->phone, 'new' => $parsed['phone']],
            ['field' => 'membership_number', 'label' => 'Membership #', 'old' => $membership?->membership_number, 'new' => $parsed['membership_number']],
            ['field' => 'is_active', 'label' => 'Status', 'old' => $membership ? ($membership->is_active ? 'Active' : 'Inactive') : null, 'new' => $parsed['is_active'] ? 'Active' : 'Inactive'],
            ['field' => 'is_visible', 'label' => 'Visibility', 'old' => $membership ? ($membership->is_visible ? 'Visible' : 'Hidden') : null, 'new' => $parsed['is_visible'] ? 'Visible' : 'Hidden'],
            ['field' => 'job_title', 'label' => 'Job title', 'old' => $oldJobTitle, 'new' => $parsed['job_title']],
            ['field' => 'company', 'label' => 'Company', 'old' => $oldCompany, 'new' => $parsed['company_name_input']],
            ['field' => 'partner', 'label' => 'Partner', 'old' => $oldPartner, 'new' => $parsed['partner_name_input']],
            ['field' => 'registration_date', 'label' => 'Registration', 'old' => $membership?->registration_date?->format('Y-m-d'), 'new' => $parsed['registration_date']],
            ['field' => 'expiration_date', 'label' => 'Expiration', 'old' => $membership?->expiration_date?->format('Y-m-d'), 'new' => $parsed['expiration_date']],
        ];

        return array_map(function ($f) {
            $oldNorm = (string) ($f['old'] ?? '');
            $newNorm = (string) ($f['new'] ?? '');
            $f['changed'] = $oldNorm !== $newNorm;

            return $f;
        }, $fields);
    }
}
