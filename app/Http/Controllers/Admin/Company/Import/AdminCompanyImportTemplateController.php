<?php

namespace App\Http\Controllers\Admin\Company\Import;

use App\Http\Controllers\Concerns\ExportsCompanyColumns;
use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCompanyImportTemplateController extends BaseController
{
    use ExportsCompanyColumns;

    /**
     * Download a file the user can fill in and import later.
     *
     *  - type=template → header row + one blank row (start from scratch)
     *  - type=example  → header row + a couple of fully-filled sample rows
     *
     * The "Companies" sheet always starts with the header on row 1, exactly
     * like the export, so the import parser handles it the same way.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $type = $request->input('type', 'template');
        $isExample = $type === 'example';

        $spreadsheet = $this->buildSpreadsheet($isExample);
        $filename = ($isExample ? 'companies_import_example_' : 'companies_import_template_')
            . now()->format('Y-m-d_His') . '.xlsx';

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

    private function buildSpreadsheet(bool $isExample): Spreadsheet
    {
        $isRtl = app()->getLocale() === 'ar';
        $defs = $this->companyColumnDefinitions();
        $keys = array_keys($defs);

        $letters = [];
        foreach ($keys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastCol = end($letters) ?: 'A';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.company_import.template_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // Header row (row 1) — same shape as the export.
        foreach ($keys as $key) {
            $sheet->setCellValue("{$letters[$key]}1", $defs[$key]['label']);
        }
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $rows = [];
        if ($isExample) {
            $rows = [
                [
                    'id'               => '',
                    'name_en'          => 'Acme Medical Center',
                    'name_ar'          => 'مركز أكيم الطبي',
                    'slug'             => 'acme-medical-center',
                    'created_by_email' => 'admin@example.com',
                    'created_at'       => '2026-01-01 10:00:00',
                    'updated_at'       => '2026-01-15 12:30:00',
                ],
                [
                    'id'               => '',
                    'name_en'          => 'Green Valley Hospital',
                    'name_ar'          => 'مستشفى الوادي الأخضر',
                    'slug'             => '',
                    'created_by_email' => '',
                    'created_at'       => '2026-02-10 09:15:00',
                    'updated_at'       => '',
                ],
            ];
        } else {
            $rows = [
                array_fill_keys($keys, ''),
            ];
        }

        $dataRow = 2;
        $rowIndex = 0;
        foreach ($rows as $row) {
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
            $sheet->getColumnDimension($letters[$key])->setWidth($defs[$key]['width']);
        }

        $this->buildInstructionsSheet($spreadsheet, $isRtl);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet, bool $isRtl): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(__('admin.company_import.instructions_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        $lines = [
            __('admin.company_import.how_to_title'),
            __('admin.company_import.how_to_1'),
            __('admin.company_import.how_to_2'),
            __('admin.company_import.how_to_3'),
            __('admin.company_import.how_to_4'),
            __('admin.company_import.how_to_5'),
            __('admin.company_import.how_to_6'),
            __('admin.company_import.how_to_7'),
            __('admin.company_import.how_to_8'),
            __('admin.company_import.how_to_9'),
        ];

        $row = 1;
        foreach ($lines as $i => $line) {
            $sheet->setCellValue("A{$row}", $line);
            if ($i === 0) {
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(30);
            }
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(110);
    }
}
