<?php

namespace App\Http\Controllers\Admin\Company\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Concerns\ExportsMemberColumns;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminCompanyMembersExportController extends BaseController
{
    use CreatorScoped;
    use ExportsMemberColumns;

    private const MIN_CHUNK_SIZE = 1;
    private const MAX_CHUNK_SIZE = 10000;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * XLSX export of a company's members — uses the same full column
     * catalogue and split-into-files behavior as the admin/user/membership
     * export (via the shared ExportsMemberColumns trait), scoped to this
     * company and honoring the same name/membership_number/phone filters as
     * the "view members" popup.
     */
    public function __invoke(Request $request, string $company): StreamedResponse
    {
        // Same locale re-resolution as the membership export: this is a
        // StreamedResponse, so HandleInertiaRequests::share() never runs.
        $locale = Session::get('locale', config('app.locale'));
        if (!in_array($locale, ['en', 'ar'], true)) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

        $company = Company::where('slug', $company)->firstOrFail();
        $this->assertOwns($company);

        $name = trim((string) $request->input('name', ''));
        $membershipNumber = trim((string) $request->input('membership_number', ''));
        $phone = trim((string) $request->input('phone', ''));

        $rawColumns = $request->input('columns', '');
        $selectedColumns = $rawColumns !== ''
            ? array_values(array_intersect(array_map('trim', explode(',', $rawColumns)), array_keys($this->getMemberColumnDefinitions())))
            : array_keys($this->getMemberColumnDefinitions());
        if (empty($selectedColumns)) {
            $selectedColumns = array_keys($this->getMemberColumnDefinitions());
        }

        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $members = $company->memberships()
            ->with(['user', 'company', 'partner', 'creator:id,name,email', 'sales', 'governorate', 'city', 'memberPayments', 'latestActiveHistory'])
            ->withCount('familyMembers')
            ->when($name !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $name . '%')))
            ->when($membershipNumber !== '', fn ($q) => $q->where('membership_number', 'like', '%' . $membershipNumber . '%'))
            ->when($phone !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('phone', 'like', '%' . $phone . '%')))
            ->orderBy('id')
            ->get();

        $companyName = $company->getTranslation('name', app()->getLocale())
            ?: ($company->getTranslation('name', 'ar') ?: $company->getTranslation('name', 'en'));

        $timestamp = now()->format('Y-m-d_His');

        if ($chunkSize === 0 || $members->count() <= $chunkSize) {
            $spreadsheet = $this->buildSpreadsheet($members, $companyName, $name, $membershipNumber, $phone, $selectedColumns);
            $filename = 'company_members_' . $company->slug . '_' . $timestamp . '.xlsx';
            return $this->streamXlsx($spreadsheet, $filename);
        }

        // Split mode: one XLSX per chunk, bundled into a ZIP.
        $chunks = $members->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir() . '/company_members_export_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = "Part {$partNumber} of {$totalParts}";
            $partSpreadsheet = $this->buildSpreadsheet($chunk, $companyName, $name, $membershipNumber, $phone, $selectedColumns, $partLabel);
            $partFilename = sprintf('members_part_%02d_of_%02d.xlsx', $partNumber, $totalParts);
            $partPath = $tmpDir . '/' . $partFilename;
            (IOFactory::createWriter($partSpreadsheet, 'Xlsx'))->save($partPath);
            $partSpreadsheet->disconnectWorksheets();
            unset($partSpreadsheet);
            $partFiles[] = ['path' => $partPath, 'name' => $partFilename];
        }

        $zipName = sprintf('company_members_%s_%s_split_%d.zip', $company->slug, $timestamp, $chunkSize);
        $zipPath = $tmpDir . '/' . $zipName;
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($partFiles as $part) {
            $zip->addFile($part['path'], $part['name']);
        }
        $zip->close();

        return response()->stream(function () use ($zipPath, $tmpDir) {
            readfile($zipPath);
            foreach (glob($tmpDir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
            'Content-Length' => filesize($zipPath),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
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

    /**
     * @param Collection<int, Membership> $members
     */
    private function buildSpreadsheet(
        Collection $members,
        string $companyName,
        string $nameFilter,
        string $membershipNumberFilter,
        string $phoneFilter,
        array $selectedColumns,
        ?string $partLabel = null
    ): Spreadsheet {
        $isRtl = app()->getLocale() === 'ar';

        $allDefs = $this->getMemberColumnDefinitions();
        if (!empty($selectedColumns)) {
            $allDefs = array_intersect_key($allDefs, array_flip($selectedColumns));
        }
        $colKeys = array_keys($allDefs);

        $letters = [];
        foreach ($colKeys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastLetter = count($letters) ? end($letters) : 'A';
        $firstCol = reset($letters) ?: 'A';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.member_export.members_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // ------ Title block ------
        $exportTitle = __('admin.company_member_export.title', ['company' => $companyName]);
        $title = $partLabel ? "{$exportTitle} — {$partLabel}" : $exportTitle;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastLetter}1");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', __('admin.company_member_export.generated_at'));
        $sheet->setCellValue('B2', now()->translatedFormat('D, d M Y H:i'));
        $sheet->setCellValue('A3', __('admin.company_member_export.total_rows'));
        $sheet->setCellValue('B3', $members->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Filter block ------
        $sheet->setCellValue('A5', __('admin.member_export.filters_applied'));
        $sheet->mergeCells("A5:{$lastLetter}5");
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $none = __('admin.member_export.value_none');
        $filterRows = [
            [__('admin.member_export.filter_company'), $companyName],
            [__('admin.member_export.filter_search'), $nameFilter !== '' ? $nameFilter : $none],
            [__('admin.member_export.filter_membership_number'), $membershipNumberFilter !== '' ? $membershipNumberFilter : $none],
            [__('admin.member_export.filter_phone'), $phoneFilter !== '' ? $phoneFilter : $none],
        ];
        $row = 6;
        foreach ($filterRows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $filterEnd = $row - 1;
        $sheet->getStyle("A6:A{$filterEnd}")->getFont()->setBold(true);
        $sheet->getStyle("A6:B{$filterEnd}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        // ------ Members table ------
        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", __('admin.member_export.members_section'));
        $sheet->mergeCells("A{$headerRow}:{$lastLetter}{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        foreach ($colKeys as $key) {
            $sheet->setCellValue("{$letters[$key]}{$columnHeaderRow}", $allDefs[$key]['label']);
        }
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("{$firstCol}{$columnHeaderRow}:{$lastLetter}{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        // ------ Data rows ------
        $dataStart = $columnHeaderRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;
        foreach ($members as $membership) {
            $user = $membership->user;
            if ($user === null) {
                continue;
            }
            $rowIndex++;

            foreach ($colKeys as $key) {
                $col = $letters[$key];
                $sheet->setCellValueExplicit("{$col}{$dataRow}", $this->getColumnValue($key, $user, $membership, $rowIndex), DataType::TYPE_STRING);
            }

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("{$firstCol}{$dataRow}:{$lastLetter}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            foreach ($colKeys as $key) {
                $def = $allDefs[$key];
                if (isset($def['align'])) {
                    $sheet->getStyle("{$letters[$key]}{$dataRow}")->getAlignment()->setHorizontal($def['align']);
                }
            }

            $badgeMap = [
                'status' => $membership->is_active
                    ? ['bg' => 'D1FAE5', 'fg' => '047857']
                    : ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
                'payment' => $membership->is_paid
                    ? ['bg' => 'D1FAE5', 'fg' => '065F46']
                    : ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
                'visibility' => $membership->is_visible
                    ? ['bg' => 'DBEAFE', 'fg' => '1D4ED8']
                    : ['bg' => 'FFEDD5', 'fg' => 'C2410C'],
                'card_patch' => isset($this->getCardBatchMap()[$membership->id])
                    ? ['bg' => 'E0F2FE', 'fg' => '0369A1']
                    : null,
            ];
            foreach (['status', 'payment', 'visibility', 'card_patch'] as $badgeKey) {
                if (isset($letters[$badgeKey]) && isset($badgeMap[$badgeKey])) {
                    $colors = $badgeMap[$badgeKey];
                    if ($colors !== null) {
                        $sheet->getStyle("{$letters[$badgeKey]}{$dataRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $colors['fg']]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['bg']]],
                        ]);
                    }
                }
            }
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        // ------ Column widths & visibility ------
        foreach ($colKeys as $key) {
            $def = $allDefs[$key];
            $sheet->getColumnDimension($letters[$key])->setWidth($def['width']);
            if (!empty($def['hidden'])) {
                $sheet->getColumnDimension($letters[$key])->setVisible(false);
            }
        }

        // ------ Footer ------
        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", __('admin.member_export.footer', ['count' => $members->count()]));
        $sheet->mergeCells("A{$footerRow}:{$lastLetter}{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCells('A1');

        return $spreadsheet;
    }
}
