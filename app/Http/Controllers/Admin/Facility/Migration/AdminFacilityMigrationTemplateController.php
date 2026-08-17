<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFacilityMigrationTemplateController extends BaseController
{
    public function example(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        // ── Sheet 1: Instructions (default sheet) ──
        $this->buildInstructionsSheet($spreadsheet);

        // ── Sheet 2: Example ──
        $this->buildExampleSheet($spreadsheet);

        $filename = 'facility_import_example_'.now()->format('Y-m-d').'.xlsx';

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

    public function blank(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        // ── Sheet 1: Facilities (empty) ──
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');

        $columns = [
            'A' => 'Name',
            'B' => 'Name (AR)',
            'C' => 'Slug',
            'D' => 'Facility Type',
            'E' => 'Governorate',
            'F' => 'City',
            'G' => 'Latitude',
            'H' => 'Longitude',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($sheet, 1, 'H');

        $widths = ['A' => 30, 'B' => 30, 'C' => 28, 'D' => 22, 'E' => 22, 'F' => 22, 'G' => 14, 'H' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // ── Sheet 2: Branches (empty) ──
        $branchSheet = $spreadsheet->createSheet();
        $branchSheet->setTitle('Branches');

        $branchColumns = [
            'A' => 'Facility Name',
            'B' => 'Branch Name',
            'C' => 'Branch Name (AR)',
            'D' => 'Address',
            'E' => 'Address (AR)',
            'F' => 'Phone',
            'G' => 'Governorate',
            'H' => 'City',
            'I' => 'Latitude',
            'J' => 'Longitude',
        ];

        foreach ($branchColumns as $col => $label) {
            $branchSheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($branchSheet, 1, 'J');

        $branchWidths = ['A' => 30, 'B' => 28, 'C' => 28, 'D' => 36, 'E' => 36, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 14, 'J' => 14];
        foreach ($branchWidths as $col => $w) {
            $branchSheet->getColumnDimension($col)->setWidth($w);
        }

        $filename = 'facility_import_template_'.now()->format('Y-m-d').'.xlsx';

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

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Instructions');

        $sheet->setCellValue('A1', 'FACILITY IMPORT — INSTRUCTIONS');
        $sheet->mergeCells('A1:F1');
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);

        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(80);

        $instructions = [
            '',
            'GENERAL',
            '• This file has two sheets: "Facilities" and "Branches".',
            '• Fill in the "Facilities" sheet first, then the "Branches" sheet.',
            '• The "Facilities" sheet requires at minimum: Name, Name (AR), and Facility Type.',
            '• The "Branches" sheet is optional — only add rows if the facility has physical branches.',
            '',
            'FACILITIES SHEET — COLUMN GUIDE',
            '',
            'Name',
            '  The facility name in English. Required.',
            '  Example: "El Gouna Medical Center"',
            '',
            'Name (AR)',
            '  The facility name in Arabic. Required.',
            '  Example: "مركز الغردقة الطبي"',
            '',
            'Slug',
            '  URL-friendly identifier (auto-generated if left empty).',
            '  Example: "el-gouna-medical-center"',
            '',
            'Facility Type',
            '  Must match an existing facility type name (EN or AR).',
            '  Example: "Clinic" or "عيادة"',
            '',
            'Governorate',
            '  Must match an existing governorate name (EN or AR).',
            '  Example: "Red Sea" or "البحر الأحمر"',
            '',
            'City',
            '  Must match an existing city name (EN or AR). Optional.',
            '  Example: "El Gouna" or "الغردقة"',
            '',
            'Latitude',
            '  Decimal number between -90 and 90. Optional.',
            '  Example: 27.3977',
            '',
            'Longitude',
            '  Decimal number between -180 and 180. Optional.',
            '  Example: 33.6596',
            '',
            '',
            'BRANCHES SHEET — COLUMN GUIDE',
            '',
            'Facility Name',
            '  Must exactly match the "Name" from the Facilities sheet. Required.',
            '',
            'Branch Name',
            '  The branch name in English.',
            '',
            'Branch Name (AR)',
            '  The branch name in Arabic.',
            '',
            'Address / Address (AR)',
            '  Branch address in English and Arabic.',
            '',
            'Phone',
            '  Comma-separated phone numbers. Example: "+20 123 456 7890, +20 987 654 3210"',
            '',
            'Governorate / City',
            '  Must match existing names.',
            '',
            'Latitude / Longitude',
            '  Decimal coordinates.',
            '',
            '',
            'IMPORTANT NOTES',
            '• Match names EXACTLY — the import matches by name, so typos create new facilities.',
            '• Duplicate names across different facilities will cause unpredictable matching.',
            '• Branches without a matching Facility Name will be skipped.',
            '• Preview your import before committing to catch errors early.',
        ];

        $row = 2;
        foreach ($instructions as $line) {
            $sheet->setCellValue("B{$row}", $line);
            $row++;
        }

        // Style section headers
        $headers = ['GENERAL', 'FACILITIES SHEET — COLUMN GUIDE', 'BRANCHES SHEET — COLUMN GUIDE', 'IMPORTANT NOTES'];
        foreach ($instructions as $i => $line) {
            $r = $i + 2;
            if (in_array($line, $headers, true)) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']],
                ]);
            }
            if (preg_match('/^(Name|Slug|Facility Type|Governorate|City|Latitude|Longitude|Facility Name|Branch Name|Phone|Address|IMPORTANT NOTES)$/i', trim($line))) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '4F46E5']],
                ]);
            }
        }
    }

    private function buildExampleSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Example');

        $columns = [
            'A' => 'Name',
            'B' => 'Name (AR)',
            'C' => 'Slug',
            'D' => 'Facility Type',
            'E' => 'Governorate',
            'F' => 'City',
            'G' => 'Latitude',
            'H' => 'Longitude',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($sheet, 1, 'H');

        $examples = [
            ['El Gouna Medical Center', 'مركز الغردقة الطبي', 'el-gouna-medical-center', 'Clinic', 'Red Sea', 'El Gouna', 27.3977, 33.6596],
            ['Cairo Dental Clinic', 'عيادة أسنان القاهرة', 'cairo-dental-clinic', 'Dental Clinic', 'Cairo', 'Nasr City', 30.0561, 31.3389],
            ['Alexandria Eye Hospital', 'مستشفى العيون بالإسكندرية', 'alexandria-eye-hospital', 'Hospital', 'Alexandria', 'Smouha', 31.2001, 29.9187],
        ];

        foreach ($examples as $i => $row) {
            $r = $i + 2;
            foreach ($row as $j => $value) {
                $col = chr(ord('A') + $j);
                $sheet->setCellValue("{$col}{$r}", $value);
            }
            $stripe = ($i % 2 === 0) ? 'F0FDF4' : 'FFFFFF';
            $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(22);
        }

        // Branch examples sheet
        $branchSheet = $spreadsheet->createSheet();
        $branchSheet->setTitle('Branch Examples');

        $branchColumns = [
            'A' => 'Facility Name',
            'B' => 'Branch Name',
            'C' => 'Branch Name (AR)',
            'D' => 'Address',
            'E' => 'Address (AR)',
            'F' => 'Phone',
            'G' => 'Governorate',
            'H' => 'City',
            'I' => 'Latitude',
            'J' => 'Longitude',
        ];

        foreach ($branchColumns as $col => $label) {
            $branchSheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($branchSheet, 1, 'J');

        $branchExamples = [
            ['El Gouna Medical Center', 'El Gouna Branch', 'فرع الغردقة', 'Abu Tig Marina, El Gouna', 'مارينا أبو تيج، الغردقة', '+20 65 358 0123', 'Red Sea', 'El Gouna', 27.3977, 33.6596],
            ['El Gouna Medical Center', 'Soma Bay Branch', 'فرع سوما باي', 'Soma Bay Resort', 'منتجع سوما باي', '+20 65 358 0456', 'Red Sea', 'Soma Bay', 27.1044, 33.8350],
            ['Cairo Dental Clinic', 'Main Branch', 'الفرع الرئيسي', '15 Abbas El Akkad St, Nasr City', 'شارع Abbas El Akkad، مدينة نصر', '+20 2 2273 0000', 'Cairo', 'Nasr City', 30.0561, 31.3389],
        ];

        foreach ($branchExamples as $i => $row) {
            $r = $i + 2;
            foreach ($row as $j => $value) {
                $col = chr(ord('A') + $j);
                $branchSheet->setCellValue("{$col}{$r}", $value);
            }
            $stripe = ($i % 2 === 0) ? 'F0FDF4' : 'FFFFFF';
            $branchSheet->getStyle("A{$r}:J{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $branchSheet->getRowDimension($r)->setRowHeight(22);
        }

        $widths = ['A' => 30, 'B' => 30, 'C' => 28, 'D' => 22, 'E' => 22, 'F' => 22, 'G' => 14, 'H' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $branchWidths = ['A' => 30, 'B' => 22, 'C' => 22, 'D' => 36, 'E' => 36, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 14, 'J' => 14];
        foreach ($branchWidths as $col => $w) {
            $branchSheet->getColumnDimension($col)->setWidth($w);
        }
    }

    private function styleHeaderRow($sheet, int $row, string $lastCol): void
    {
        $sheet->getRowDimension($row)->setRowHeight(26);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);
    }
}
