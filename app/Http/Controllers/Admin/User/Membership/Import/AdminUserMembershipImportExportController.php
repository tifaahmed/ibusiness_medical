<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Partner;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUserMembershipImportExportController extends BaseController
{
    use ScopesByMembershipCreator;

    private const MEMBERS_COLUMNS = [
        'Name' => ['label' => 'Name', 'width' => 36],
        'Email' => ['label' => 'Email', 'width' => 32],
        'Phone' => ['label' => 'Phone', 'width' => 20],
        'Membership #' => ['label' => 'Membership #', 'width' => 22],
        'Status' => ['label' => 'Status', 'width' => 14],
        'Visibility' => ['label' => 'Visibility', 'width' => 16],
        'Job title' => ['label' => 'Job title', 'width' => 30],
        'Company' => ['label' => 'Company', 'width' => 30],
        'Company id' => ['label' => 'Company id', 'width' => 14],
        'Partner' => ['label' => 'Partner', 'width' => 28],
        'Partner id' => ['label' => 'Partner id', 'width' => 14],
        'Registration date' => ['label' => 'Registration date', 'width' => 20],
        'Expiration date' => ['label' => 'Expiration date', 'width' => 20],
        'Avatar url' => ['label' => 'Avatar url', 'width' => 46],
    ];

    private const FAMILY_COLUMNS = [
        'Member name' => ['label' => 'Member name', 'width' => 36],
        'Membership #' => ['label' => 'Membership #', 'width' => 22],
        'Family member name' => ['label' => 'Family member name', 'width' => 36],
        'Relationship' => ['label' => 'Relationship', 'width' => 18],
        'Date of birth' => ['label' => 'Date of birth', 'width' => 18],
        'Phone' => ['label' => 'Phone', 'width' => 20],
        'Email' => ['label' => 'Email', 'width' => 30],
        'Status' => ['label' => 'Status', 'width' => 14],
        'Photo url' => ['label' => 'Photo url', 'width' => 46],
    ];

    private const PAYMENTS_COLUMNS = [
        'Membership #' => ['label' => 'Membership #', 'width' => 22],
        'Amount' => ['label' => 'Amount', 'width' => 16],
        'Months paid' => ['label' => 'Months paid', 'width' => 16],
        'From date' => ['label' => 'From date', 'width' => 18],
        'To date' => ['label' => 'To date', 'width' => 18],
        'Notes' => ['label' => 'Notes', 'width' => 40],
        'Type' => ['label' => 'Type', 'width' => 18],
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $locale = session('locale', config('app.locale'));

        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $listingFilter = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        $search = $request->input('search', '');
        $email = $request->input('email', '');
        $phone = $request->input('phone', '');
        $membershipNumber = $request->input('membership_number', '');
        $isActive = $request->has('is_active') ? (bool) $request->input('is_active') : null;
        $isPaid = $request->has('is_paid') ? (bool) $request->input('is_paid') : null;
        $partnerId = $request->filled('partner_id') ? (int) $request->input('partner_id') : null;
        $creatorId = $request->filled('creator_id') ? (int) $request->input('creator_id') : null;
        $saleId = $request->filled('sale_id') ? (int) $request->input('sale_id') : null;
        $companyId = $request->filled('company_id') ? (int) $request->input('company_id') : null;
        $createdFrom = $request->filled('created_from') ? $request->input('created_from') : null;
        $createdTo = $request->filled('created_to') ? $request->input('created_to') : null;
        $regFrom = $request->filled('registration_date_from') ? $request->input('registration_date_from') : null;
        $regTo = $request->filled('registration_date_to') ? $request->input('registration_date_to') : null;
        $expFrom = $request->filled('expiration_date_from') ? $request->input('expiration_date_from') : null;
        $expTo = $request->filled('expiration_date_to') ? $request->input('expiration_date_to') : null;
        $lastActChanger = $request->input('last_activation_changer', '');
        $lastActFrom = $request->filled('last_activation_from') ? $request->input('last_activation_from') : null;
        $lastActTo = $request->filled('last_activation_to') ? $request->input('last_activation_to') : null;

        $memberships = Membership::query()
            ->where(function ($mq) use ($listingFilter) {
                $listingFilter($mq);
            })
            ->when(! empty($search), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%')))
            ->when(! empty($email), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('email', 'like', '%'.$email.'%')))
            ->when(! empty($phone), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('phone', 'like', '%'.$phone.'%')))
            ->when(! empty($membershipNumber), fn ($q) => $q->where('membership_number', 'like', '%'.$membershipNumber.'%'))
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->when($isPaid !== null, fn ($q) => $q->where('is_paid', $isPaid))
            ->when($partnerId !== null, fn ($q) => $q->where('partner_id', $partnerId))
            ->when($creatorId !== null, fn ($q) => $q->where('created_by', $creatorId))
            ->when($saleId !== null, fn ($q) => $q->where('sales_id', $saleId))
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->when(! empty($createdFrom), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->whereDate('created_at', '>=', $createdFrom)))
            ->when(! empty($createdTo), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->whereDate('created_at', '<=', $createdTo)))
            ->when(! empty($regFrom), fn ($q) => $q->whereDate('registration_date', '>=', $regFrom))
            ->when(! empty($regTo), fn ($q) => $q->whereDate('registration_date', '<=', $regTo))
            ->when(! empty($expFrom), fn ($q) => $q->whereDate('expiration_date', '>=', $expFrom))
            ->when(! empty($expTo), fn ($q) => $q->whereDate('expiration_date', '<=', $expTo))
            ->when(! empty($lastActChanger), fn ($q) => $q->whereHas('activeHistories', fn ($ahq) => $ahq->whereHas('changer', fn ($cq) => $cq->where('name', 'like', '%'.$lastActChanger.'%'))))
            ->when(! empty($lastActFrom), fn ($q) => $q->whereHas('activeHistories', fn ($ahq) => $ahq->whereDate('created_at', '>=', $lastActFrom)))
            ->when(! empty($lastActTo), fn ($q) => $q->whereHas('activeHistories', fn ($ahq) => $ahq->whereDate('created_at', '<=', $lastActTo)))
            ->with([
                'user:id,name,email,phone',
                'company:id,name',
                'partner:id,title',
                'familyMembers',
                'memberPayments',
            ])
            ->orderBy('id')
            ->get();

        $companies = Company::orderBy('id')->get();
        $partners = Partner::orderBy('title')->get();

        $spreadsheet = $this->buildSpreadsheet($memberships, $companies, $partners, $locale);

        $timestamp = now()->format('Y-m-d_His');
        $filename = "members_import_export_{$timestamp}.xlsx";

        return response()->stream(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function buildSpreadsheet($memberships, $companies, $partners, string $locale): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $companyName = fn ($c) => $c->getTranslation('name', $locale)
            ?: ($c->getTranslation('name', 'ar') ?: $c->getTranslation('name', 'en'));

        // ── Members sheet ──────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Members');

        $keys = array_keys(self::MEMBERS_COLUMNS);
        $letters = [];
        foreach ($keys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastCol = end($letters) ?: 'A';

        foreach ($keys as $key) {
            $sheet->setCellValue("{$letters[$key]}1", self::MEMBERS_COLUMNS[$key]['label']);
        }
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataRow = 2;
        $rowIndex = 0;
        foreach ($memberships as $membership) {
            $user = $membership->user;
            if (! $user) {
                continue;
            }
            $rowIndex++;

            $jobTitle = $membership->getTranslation('job_title', $locale)
                ?: ($membership->getTranslation('job_title', 'ar') ?: ($membership->getTranslation('job_title', 'en') ?: ''));

            $row = [
                'Name' => (string) $user->name,
                'Email' => (string) ($user->email ?? ''),
                'Phone' => (string) ($user->phone ?? ''),
                'Membership #' => (string) $membership->membership_number,
                'Status' => $membership->is_active ? 'active' : 'inactive',
                'Visibility' => $membership->is_visible ? 'visible' : 'hidden',
                'Job title' => $jobTitle,
                'Company' => $membership->company ? $companyName($membership->company) : '',
                'Company id' => $membership->company_id ? (string) $membership->company_id : '',
                'Partner' => $membership->partner?->title ?? '',
                'Partner id' => $membership->partner_id ? (string) $membership->partner_id : '',
                'Registration date' => $membership->registration_date?->format('Y-m-d') ?? '',
                'Expiration date' => $membership->expiration_date?->format('Y-m-d') ?? '',
                'Avatar url' => get_image_url($user, 'avatar'),
            ];

            foreach ($keys as $key) {
                $sheet->setCellValueExplicit("{$letters[$key]}{$dataRow}", (string) ($row[$key] ?? ''), DataType::TYPE_STRING);
            }

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            $dataRow++;
        }

        foreach ($keys as $key) {
            $sheet->getColumnDimension($letters[$key])->setWidth(self::MEMBERS_COLUMNS[$key]['width']);
        }

        // ── Family Members sheet ───────────────────────────────────
        $famSheet = $spreadsheet->createSheet();
        $famSheet->setTitle('Family Members');

        $famKeys = array_keys(self::FAMILY_COLUMNS);
        $famLetters = [];
        foreach ($famKeys as $i => $key) {
            $famLetters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $famLastCol = end($famLetters) ?: 'A';

        foreach ($famKeys as $key) {
            $famSheet->setCellValue("{$famLetters[$key]}1", self::FAMILY_COLUMNS[$key]['label']);
        }
        $famSheet->getStyle("A1:{$famLastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $famDataRow = 2;
        $famIndex = 0;
        foreach ($memberships as $membership) {
            if ($membership->familyMembers->isEmpty()) {
                continue;
            }
            $userName = $membership->user?->name ?? '';
            foreach ($membership->familyMembers as $fm) {
                $famIndex++;
                $famRow = [
                    'Member name' => $userName,
                    'Membership #' => (string) $membership->membership_number,
                    'Family member name' => (string) $fm->name,
                    'Relationship' => $fm->relationship instanceof RelationshipEnum
                        ? $fm->relationship->value
                        : (string) $fm->getRawOriginal('relationship'),
                    'Date of birth' => $fm->date_of_birth?->format('Y-m-d') ?? '',
                    'Phone' => (string) ($fm->phone ?? ''),
                    'Email' => (string) ($fm->email ?? ''),
                    'Status' => $fm->is_active ? 'active' : 'inactive',
                    'Photo url' => get_image_url($fm, 'photo'),
                ];

                foreach ($famKeys as $key) {
                    $famSheet->setCellValueExplicit("{$famLetters[$key]}{$famDataRow}", (string) ($famRow[$key] ?? ''), DataType::TYPE_STRING);
                }

                $stripe = ($famIndex % 2 === 0) ? 'F3E8FF' : 'FFFFFF';
                $famSheet->getStyle("A{$famDataRow}:{$famLastCol}{$famDataRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);

                $famDataRow++;
            }
        }

        foreach ($famKeys as $key) {
            $famSheet->getColumnDimension($famLetters[$key])->setWidth(self::FAMILY_COLUMNS[$key]['width']);
        }

        // ── Payments sheet ─────────────────────────────────────────
        $paySheet = $spreadsheet->createSheet();
        $paySheet->setTitle('Payments');

        $payKeys = array_keys(self::PAYMENTS_COLUMNS);
        $payLetters = [];
        foreach ($payKeys as $i => $key) {
            $payLetters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $payLastCol = end($payLetters) ?: 'A';

        foreach ($payKeys as $key) {
            $paySheet->setCellValue("{$payLetters[$key]}1", self::PAYMENTS_COLUMNS[$key]['label']);
        }
        $paySheet->getStyle("A1:{$payLastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $payDataRow = 2;
        $payIndex = 0;
        foreach ($memberships as $membership) {
            if ($membership->memberPayments->isEmpty()) {
                continue;
            }
            foreach ($membership->memberPayments as $payment) {
                $payIndex++;
                $payRow = [
                    'Membership #' => (string) $membership->membership_number,
                    'Amount' => (string) $payment->amount,
                    'Months paid' => (string) $payment->months_paid,
                    'From date' => $payment->from_date?->format('Y-m-d') ?? '',
                    'To date' => $payment->to_date?->format('Y-m-d') ?? '',
                    'Notes' => (string) ($payment->notes ?? ''),
                    'Type' => (string) ($payment->type ?? 'commission'),
                ];

                foreach ($payKeys as $key) {
                    $paySheet->setCellValueExplicit("{$payLetters[$key]}{$payDataRow}", (string) ($payRow[$key] ?? ''), DataType::TYPE_STRING);
                }

                $stripe = ($payIndex % 2 === 0) ? 'ECFDF5' : 'FFFFFF';
                $paySheet->getStyle("A{$payDataRow}:{$payLastCol}{$payDataRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);

                $payDataRow++;
            }
        }

        foreach ($payKeys as $key) {
            $paySheet->getColumnDimension($payLetters[$key])->setWidth(self::PAYMENTS_COLUMNS[$key]['width']);
        }

        // ── Reference sheet ────────────────────────────────────────
        $this->buildReferenceSheet($spreadsheet, $companies, $partners, $locale);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildReferenceSheet(Spreadsheet $spreadsheet, $companies, $partners, string $locale): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Available Values');
        $sheet->setRightToLeft(false);

        // ── Companies ──────────────────────────────────────────────
        $sheet->setCellValue('A1', 'AVAILABLE COMPANIES');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
        ]);

        $sheet->setCellValue('B1', 'Copy-paste this name into the Company column (or use the ID in "Company id")');
        $sheet->getStyle('B1')->getFont()->setItalic(true);

        $sheet->setCellValue('A2', '#');
        $sheet->setCellValue('B2', 'Company Name');
        $sheet->setCellValue('C2', 'Company ID');
        $sheet->getStyle('A2:C2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B7280']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $row = 3;
        $index = 0;
        foreach ($companies as $company) {
            $index++;
            $name = $company->getTranslation('name', $locale)
                ?: ($company->getTranslation('name', 'ar') ?: $company->getTranslation('name', 'en'));
            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $name);
            $sheet->setCellValue("C{$row}", $company->id);
            $stripe = ($index % 2 === 0) ? 'F3F4F6' : 'FFFFFF';
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('C')->setWidth(14);

        // ── Partners ───────────────────────────────────────────────
        $row += 2;
        $sheet->setCellValue("A{$row}", 'AVAILABLE PARTNERS');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $row++;
        $sheet->setCellValue("B{$row}", 'Copy-paste this name into the Partner column (or use the ID in "Partner id")');
        $sheet->getStyle("B{$row}")->getFont()->setItalic(true);

        $row++;
        $sheet->setCellValue("A{$row}", '#');
        $sheet->setCellValue("B{$row}", 'Partner Name');
        $sheet->setCellValue("C{$row}", 'Partner ID');
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B7280']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $row++;
        $index = 0;
        foreach ($partners as $partner) {
            $index++;
            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $partner->title);
            $sheet->setCellValue("C{$row}", $partner->id);
            $stripe = ($index % 2 === 0) ? 'F9F5E8' : 'FFFFFF';
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $row++;
        }

        // ── Family Relationships ───────────────────────────────────
        $row += 2;
        $sheet->setCellValue("A{$row}", 'AVAILABLE FAMILY RELATIONSHIPS');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
        ]);

        $row++;
        $sheet->setCellValue("B{$row}", 'Copy-paste this value into the Relationship column in the Family Members sheet');
        $sheet->getStyle("B{$row}")->getFont()->setItalic(true);

        $row++;
        $sheet->setCellValue("A{$row}", '#');
        $sheet->setCellValue("B{$row}", 'Relationship');
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B7280']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $row++;
        $index = 0;
        foreach (RelationshipEnum::values() as $value) {
            $index++;
            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $value);
            $stripe = ($index % 2 === 0) ? 'F3E8FF' : 'FFFFFF';
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $row++;
        }

        // ── Payment Types ──────────────────────────────────────────
        $row += 2;
        $sheet->setCellValue("A{$row}", 'PAYMENT TYPES');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
        ]);

        $row++;
        $sheet->setCellValue("B{$row}", 'Copy-paste this value into the Type column in the Payments sheet (or type any custom value)');
        $sheet->getStyle("B{$row}")->getFont()->setItalic(true);

        $row++;
        $sheet->setCellValue("A{$row}", '#');
        $sheet->setCellValue("B{$row}", 'Payment Type');
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B7280']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $row++;
        $sheet->setCellValue("A{$row}", 1);
        $sheet->setCellValue("B{$row}", 'commission');
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
    }
}
