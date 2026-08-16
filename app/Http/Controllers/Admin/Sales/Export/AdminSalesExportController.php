<?php

namespace App\Http\Controllers\Admin\Sales\Export;

use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Enums\User\UserPermissionEnum;
use App\Models\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSalesExportController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SALES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SALES; }

    /**
     * Export every sales row the current user can see to an XLSX file that is
     * shaped exactly like the import template: the header row (located by the
     * "#" / "Name" signature) plus the full data table including the id column.
     * Re-importing this file preserves ids, names, created_by and the image url.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        // StreamedResponse never runs HandleInertiaRequests::share(), so
        // re-resolve the session locale exactly like the membership export does.
        $locale = Session::get('locale', config('app.locale'));
        if (!in_array($locale, ['en', 'ar'], true)) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

        $sales = Sales::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->with('creator:id,name,email')
            ->orderBy('id')
            ->get();

        $timestamp = now()->format('Y-m-d_His');
        $spreadsheet = $this->buildSpreadsheet($sales);
        $filename = 'sales_export_' . $timestamp . '.xlsx';

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
     * @param Collection<int, Sales> $sales
     */
    private function buildSpreadsheet(Collection $sales): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales');

        // ------ Title block ------
        $sheet->setCellValue('A1', 'SALES EXPORT');
        $sheet->mergeCells('A1:G1');
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', 'Generated at:');
        $sheet->setCellValue('B2', now()->translatedFormat('D, d M Y H:i'));
        $sheet->setCellValue('A3', 'Total rows:');
        $sheet->setCellValue('B3', $sales->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Data table ------
        $columnHeaderRow = 5;
        $columns = [
            'A' => '#', 'B' => 'Name', 'C' => 'Name (AR)',
            'D' => 'Image url', 'E' => 'Created by',
            'F' => 'Created at', 'G' => 'Updated at',
        ];
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$columnHeaderRow}", $label);
        }
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("A{$columnHeaderRow}:G{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataRow = $columnHeaderRow + 1;
        $rowIndex = 0;
        foreach ($sales as $sale) {
            $rowIndex++;
            $nameEn = (string) ($sale->getTranslation('name', 'en') ?: '');
            $nameAr = (string) ($sale->getTranslation('name', 'ar') ?: '');
            $creator = $sale->creator;
            $creatorCell = $creator ? $creator->id . ' ' . trim($creator->name) : '';

            $sheet->setCellValue("A{$dataRow}", (string) $sale->id);
            $sheet->setCellValue("B{$dataRow}", $nameEn);
            $sheet->setCellValue("C{$dataRow}", $nameAr);
            $sheet->setCellValue("D{$dataRow}", (string) ($sale->image ?: ''));
            $sheet->setCellValue("E{$dataRow}", $creatorCell);
            $sheet->setCellValue("F{$dataRow}", $sale->created_at?->format('d M Y H:i') ?? '');
            $sheet->setCellValue("G{$dataRow}", $sale->updated_at?->format('d M Y H:i') ?? '');

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:G{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$dataRow}:G{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        $widths = ['A' => 8, 'B' => 36, 'C' => 36, 'D' => 48, 'E' => 24, 'F' => 22, 'G' => 22];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $footerRow = $dataRow + 1;
        $sheet->setCellValue("A{$footerRow}", 'END OF REPORT — ' . $sales->count() . ' sales row(s) exported');
        $sheet->mergeCells("A{$footerRow}:G{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }
}
