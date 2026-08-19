<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
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

class AdminUserMembershipImportTemplateController extends BaseController
{
    private const MEMBERS_COLUMNS = [
        'Name' => ['label' => 'Name', 'width' => 36, 'required' => true],
        'Email' => ['label' => 'Email', 'width' => 32],
        'Phone' => ['label' => 'Phone', 'width' => 20],
        'Membership #' => ['label' => 'Membership #', 'width' => 22, 'required' => true],
        'Status' => ['label' => 'Status', 'width' => 14],
        'Visibility' => ['label' => 'Visibility', 'width' => 16],
        'Job title' => ['label' => 'Job title', 'width' => 30],
        'Company' => ['label' => 'Company', 'width' => 30],
        'Company id' => ['label' => 'Company id', 'width' => 14],
        'Partner' => ['label' => 'Partner', 'width' => 28],
        'Partner id' => ['label' => 'Partner id', 'width' => 14],
        'Registration date' => ['label' => 'Registration date', 'width' => 20],
        'Expiration date' => ['label' => 'Expiration date', 'width' => 20, 'required' => true],
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
        'Membership #' => ['label' => 'Membership #', 'width' => 22, 'required' => true],
        'Amount' => ['label' => 'Amount', 'width' => 16, 'required' => true],
        'Months paid' => ['label' => 'Months paid', 'width' => 16, 'required' => true],
        'From date' => ['label' => 'From date', 'width' => 18, 'required' => true],
        'To date' => ['label' => 'To date', 'width' => 18, 'required' => true],
        'Notes' => ['label' => 'Notes', 'width' => 40],
        'Type' => ['label' => 'Type', 'width' => 18],
    ];

    /**
     * Download a fill-in template for the member import.
     *
     * ?example=1 → includes sample rows so the user can see exactly how to
     * fill the file. Without it, only the header row (plus the "Instructions"
     * sheet) is produced — the blank file meant to be filled in and imported.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $includeExample = $request->boolean('example');

        $spreadsheet = $this->buildSpreadsheet($includeExample);
        $filename = $includeExample
            ? 'members_import_example.xlsx'
            : 'members_import_template.xlsx';

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

    private function buildSpreadsheet(bool $includeExample): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $companies = Company::orderBy('id')->get();
        $partners = Partner::orderBy('title')->get();

        // ── Members sheet ──────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Members');

        $keys = array_keys(self::MEMBERS_COLUMNS);
        $letters = [];
        foreach ($keys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastCol = end($letters) ?: 'A';

        // Header row
        foreach ($keys as $key) {
            $sheet->setCellValue("{$letters[$key]}1", self::MEMBERS_COLUMNS[$key]['label']);
        }
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataRows = $includeExample ? $this->getMemberExampleRows($companies, $partners) : [$this->blankMemberRow()];
        $dataRow = 2;
        $rowIndex = 0;
        foreach ($dataRows as $row) {
            $rowIndex++;
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

        // ── Family Members sheet ───────────────────────────────────────
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

        $famDataRows = $includeExample ? $this->getFamilyExampleRows() : [$this->blankFamilyRow()];
        $famDataRow = 2;
        $famRowIndex = 0;
        foreach ($famDataRows as $row) {
            $famRowIndex++;
            foreach ($famKeys as $key) {
                $famSheet->setCellValueExplicit("{$famLetters[$key]}{$famDataRow}", (string) ($row[$key] ?? ''), DataType::TYPE_STRING);
            }
            $stripe = ($famRowIndex % 2 === 0) ? 'F3E8FF' : 'FFFFFF';
            $famSheet->getStyle("A{$famDataRow}:{$famLastCol}{$famDataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $famDataRow++;
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

        $payDataRows = $includeExample ? $this->getPaymentExampleRows() : [$this->blankPaymentRow()];
        $payDataRow = 2;
        $payRowIndex = 0;
        foreach ($payDataRows as $row) {
            $payRowIndex++;
            foreach ($payKeys as $key) {
                $paySheet->setCellValueExplicit("{$payLetters[$key]}{$payDataRow}", (string) ($row[$key] ?? ''), DataType::TYPE_STRING);
            }
            $stripe = ($payRowIndex % 2 === 0) ? 'ECFDF5' : 'FFFFFF';
            $paySheet->getStyle("A{$payDataRow}:{$payLastCol}{$payDataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $payDataRow++;
        }

        foreach ($payKeys as $key) {
            $paySheet->getColumnDimension($payLetters[$key])->setWidth(self::PAYMENTS_COLUMNS[$key]['width']);
        }

        // ── Available Companies & Partners sheet ───────────────────────
        $this->buildReferenceSheet($spreadsheet, $companies, $partners);

        // ── Instructions sheet ─────────────────────────────────────────
        $this->buildInstructionsSheet($spreadsheet, $includeExample);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function getMemberExampleRows($companies, $partners): array
    {
        $companyName = function ($company) {
            return $company->getTranslation('name', app()->getLocale())
                ?: ($company->getTranslation('name', 'ar') ?: $company->getTranslation('name', 'en'));
        };
        $partnerName = fn ($partner) => $partner->title;

        $firstCompany = $companies->first();
        $secondCompany = $companies->skip(1)->first();
        $firstPartner = $partners->first();
        $secondPartner = $partners->skip(1)->first();

        return [
            [
                'Name' => 'Ahmed Al-Rashid',
                'Email' => 'ahmed@example.com',
                'Phone' => '+966501234567',
                'Membership #' => 'MEM-001',
                'Status' => 'active',
                'Visibility' => 'visible',
                'Job title' => 'General Manager',
                'Company' => $firstCompany ? $companyName($firstCompany) : '',
                'Company id' => $firstCompany ? (string) $firstCompany->id : '',
                'Partner' => $firstPartner ? $partnerName($firstPartner) : '',
                'Partner id' => $firstPartner ? (string) $firstPartner->id : '',
                'Registration date' => '2026-01-01',
                'Expiration date' => '2027-01-01',
                'Avatar url' => '',
            ],
            [
                'Name' => 'Sara Al-Fahad',
                'Email' => 'sara@example.com',
                'Phone' => '+966509876543',
                'Membership #' => 'MEM-002',
                'Status' => 'active',
                'Visibility' => 'visible',
                'Job title' => 'Marketing Director',
                'Company' => $secondCompany ? $companyName($secondCompany) : '',
                'Company id' => $secondCompany ? (string) $secondCompany->id : '',
                'Partner' => $secondPartner ? $partnerName($secondPartner) : '',
                'Partner id' => $secondPartner ? (string) $secondPartner->id : '',
                'Registration date' => '2026-03-15',
                'Expiration date' => '2027-03-15',
                'Avatar url' => '',
            ],
        ];
    }

    private function blankMemberRow(): array
    {
        $row = [];
        foreach (array_keys(self::MEMBERS_COLUMNS) as $key) {
            $row[$key] = '';
        }

        return $row;
    }

    private function getFamilyExampleRows(): array
    {
        return [
            [
                'Member name' => 'Ahmed Al-Rashid',
                'Membership #' => 'MEM-001',
                'Family member name' => 'Fatima Al-Rashid',
                'Relationship' => 'wife',
                'Date of birth' => '1990-05-20',
                'Phone' => '+966501112233',
                'Email' => 'fatima@example.com',
                'Status' => 'active',
                'Photo url' => '',
            ],
            [
                'Member name' => 'Ahmed Al-Rashid',
                'Membership #' => 'MEM-001',
                'Family member name' => 'Omar Al-Rashid',
                'Relationship' => 'son',
                'Date of birth' => '2015-08-10',
                'Phone' => '',
                'Email' => '',
                'Status' => 'active',
                'Photo url' => '',
            ],
        ];
    }

    private function blankFamilyRow(): array
    {
        $row = [];
        foreach (array_keys(self::FAMILY_COLUMNS) as $key) {
            $row[$key] = '';
        }

        return $row;
    }

    private function getPaymentExampleRows(): array
    {
        return [
            [
                'Membership #' => 'MEM-001',
                'Amount' => '500.00',
                'Months paid' => '12',
                'From date' => '2026-01-01',
                'To date' => '2026-12-31',
                'Notes' => 'Annual membership fee',
                'Type' => 'commission',
            ],
            [
                'Membership #' => 'MEM-002',
                'Amount' => '250.00',
                'Months paid' => '6',
                'From date' => '2026-03-01',
                'To date' => '2026-08-31',
                'Notes' => '',
                'Type' => 'commission',
            ],
        ];
    }

    private function blankPaymentRow(): array
    {
        $row = [];
        foreach (array_keys(self::PAYMENTS_COLUMNS) as $key) {
            $row[$key] = '';
        }

        return $row;
    }

    private function buildReferenceSheet(Spreadsheet $spreadsheet, $companies, $partners): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Available Values');
        $sheet->setRightToLeft(false);

        $locale = app()->getLocale();

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

    private function buildInstructionsSheet(Spreadsheet $spreadsheet, bool $includeExample): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');

        $title = $includeExample ? 'MEMBERS IMPORT — EXAMPLE' : 'MEMBERS IMPORT — TEMPLATE';

        $lines = [
            $title,
            '',
            'HOW TO FILL THIS FILE',
            '',
            'The file has three sheets: "Members", "Family Members", and "Payments".',
            '',
            '── MEMBERS SHEET ──',
            '',
            'Each row = one member. Required columns:',
            '   • Name                  — Full name of the member.',
            '   • Membership #          — Unique membership number (e.g. MEM-001).',
            '   • Expiration date       — When the membership expires (YYYY-MM-DD or DD/MM/YYYY).',
            '',
            'Optional columns:',
            '   • Email                 — Must be a valid email if provided.',
            '   • Phone                 — Phone number.',
            '   • Status                — "active" or "inactive". Defaults to "active".',
            '   • Visibility            — "visible" or "hidden". Defaults to "visible".',
            '   • Job title             — Member\'s job title.',
            '   • Company               — Company name (must match an existing company in the system).',
            '   • Company id            — Numeric company ID (alternative to Company name — takes priority if both are set).',
            '   • Partner               — Partner name (must match an existing partner in the system).',
            '   • Partner id            — Numeric partner ID (alternative to Partner name — takes priority if both are set).',
            '   • Registration date     — When the membership started.',
            '   • Avatar url            — Public URL for a profile image.',
            '',
            '── FAMILY MEMBERS SHEET (optional) ──',
            '',
            'Each row = one family member linked to a member via "Membership #".',
            'Columns:',
            '   • Member name           — Display only, the actual link is by Membership #.',
            '   • Membership #          — Must match a Membership # from the Members sheet.',
            '   • Family member name    — Name of the family member.',
            '   • Relationship          — e.g. wife, son, daughter.',
            '   • Date of birth         — YYYY-MM-DD or DD/MM/YYYY.',
            '   • Phone / Email         — Optional contact info.',
            '   • Status                — "active" or "inactive".',
            '',
            '── PAYMENTS SHEET (optional) ──',
            '',
            'Each row = one payment linked to a member via "Membership #".',
            'Columns:',
            '   • Membership #          — Must match a Membership # from the Members sheet.',
            '   • Amount                — Payment amount (e.g. 500.00).',
            '   • Months paid           — Number of months covered (e.g. 12).',
            '   • From date             — Start date of the payment period (YYYY-MM-DD).',
            '   • To date               — End date of the payment period (YYYY-MM-DD).',
            '   • Notes                 — Optional notes.',
            '   • Type                  — Payment type. Default is "commission". Any short text is accepted.',
            '',
            '── AVAILABLE VALUES SHEET ──',
            '',
            'Check the "Available Values" sheet for reference lists:',
            '   • Available Companies      — copy-paste exact names into the Company column, or use the Company ID.',
            '   • Available Partners       — copy-paste exact names into the Partner column, or use the Partner ID.',
            '   • Available Relationships  — valid values for the Relationship column in Family Members:',
            '       wife, husband, son, daughter, father, mother, brother, sister',
            '   • Payment Types            — "commission" is the default; any short text (max 20 chars) is accepted.',
            '',
            '── IMPORT STEPS ──',
            '',
            '1. Fill in the file (or use an export as a starting point — same column headers).',
            '2. Check the "Available Values" sheet for exact Company and Partner names (or IDs),',
            '   valid Family Relationships, and Payment Types — the import matches them by name or ID.',
            '3. On the import page, choose your import mode:',
            '   • Update existing & insert new — Members with matching Membership # are updated.',
            '   • Clear table & add new         — All existing members are soft-deleted, then new ones are inserted.',
            '4. Click "Preview" to review changes before anything is saved.',
            '5. Edit rows inline, skip errors, then click "Confirm & import".',
        ];

        $row = 1;
        foreach ($lines as $line) {
            $sheet->setCellValue("A{$row}", $line);
            if ($line === $title) {
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(30);
            } elseif ($line === 'HOW TO FILL THIS FILE' || str_starts_with($line, '──') || str_starts_with($line, '   •')) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(110);
    }
}
