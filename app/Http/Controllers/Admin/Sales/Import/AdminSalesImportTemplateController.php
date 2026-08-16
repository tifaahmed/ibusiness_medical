<?php

namespace App\Http\Controllers\Admin\Sales\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSalesImportTemplateController extends BaseController
{
    private const COLUMNS = [
        'A' => '#', 'B' => 'Name', 'C' => 'Name (AR)',
        'D' => 'Image url', 'E' => 'Created by',
        'F' => 'Created at', 'G' => 'Updated at',
    ];

    /**
     * Download a fill-in template for the sales import.
     *
     * ?example=1 → includes two sample rows so the user can see exactly how to
     * fill the file. Without it, only the header row (plus the "Instructions"
     * sheet) is produced — the blank file meant to be filled in and imported later.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $includeExample = $request->boolean('example');

        $spreadsheet = $this->buildSpreadsheet($includeExample);
        $filename = $includeExample
            ? 'sales_import_example.xlsx'
            : 'sales_import_template.xlsx';

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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales');

        $sheet->setCellValue('A1', $includeExample ? 'SALES IMPORT — EXAMPLE' : 'SALES IMPORT — TEMPLATE');
        $sheet->mergeCells('A1:G1');
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 17, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $headerRow = 3;
        foreach (self::COLUMNS as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(24);
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $row = $headerRow + 1;
        if ($includeExample) {
            $firstUserId = User::query()->orderBy('id')->value('id');
            $examples = [
                ['Pharmacy Main', 'صيدلية الرئيسية', 'https://example.com/image1.jpg', $firstUserId],
                ['Medical Center', 'المركز الطبي', '', $firstUserId],
            ];
            foreach ($examples as $i => $ex) {
                $sheet->setCellValue("A{$row}", '');
                $sheet->setCellValue("B{$row}", $ex[0]);
                $sheet->setCellValue("C{$row}", $ex[1]);
                $sheet->setCellValue("D{$row}", $ex[2]);
                $sheet->setCellValue("E{$row}", $ex[3] ?? '');
                $sheet->setCellValue("F{$row}", now()->format('d M Y H:i'));
                $sheet->setCellValue("G{$row}", now()->format('d M Y H:i'));
                $stripe = ($i % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $row++;
            }
        }

        $widths = ['A' => 10, 'B' => 34, 'C' => 34, 'D' => 50, 'E' => 16, 'F' => 22, 'G' => 22];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $this->buildInstructionsSheet($spreadsheet, $includeExample);

        return $spreadsheet;
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet, bool $includeExample): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $sheet->setRightToLeft(false);

        $lines = [
            'HOW TO FILL THIS FILE',
            '',
            '1. Each row = one sales entry. Leave the row blank if you do not want to import it.',
            '2. Columns:',
            '   - "#":            Optional. The row id. Leave EMPTY for new rows.',
            '                     If you re-import an exported file, this id is used to UPDATE the existing row.',
            '   - "Name":         The sales name in English (or Arabic). At least one name is required.',
            '   - "Name (AR)":    The sales name in Arabic. Optional if "Name" is filled.',
            '   - "Image url":    Optional. A public URL for the image. It is downloaded and attached during import.',
            '   - "Created by":   Optional. The user id. Must be an existing user id, otherwise the importer is used.',
            '   - "Created at" / "Updated at": Read-only from exports; they are ignored when importing.',
            '3. You can also start from the EXPORT button on the Sales page — that file has the same columns',
            '   and can be edited, then imported back.',
            '',
            'IMPORT STRATEGIES (chosen on the import page before confirming):',
            '   - Update:              Rows whose "#" id already exists are UPDATED. Unknown ids are inserted.',
            '   - Create:              Every row is inserted as a BRAND-NEW sales record (new auto ids).',
            '   - Delete all, then add: ALL existing sales are DELETED first, then every row is inserted',
            '                            with its exact "#" id preserved. Use for a full restore.',
            '   - Add only:            Only rows whose "#" id does NOT exist yet are inserted. Existing rows are skipped.',
            '',
            'You will see a PREVIEW before anything is written, so you can edit the names or skip rows.',
        ];
        $row = 1;
        foreach ($lines as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $bold = $line === 'HOW TO FILL THIS FILE' || str_starts_with($line, '   - ') || $line === 'IMPORT STRATEGIES (chosen on the import page before confirming):';
            if ($bold) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(120);
    }
}
