<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminFacilityMigrationTemplateController extends BaseController
{
    public function example(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        // ── Sheet 1: Example (active sheet) ──
        $this->buildExampleSheet($spreadsheet);

        // ── Sheet 2: Branch Examples ── (built inside buildExampleSheet)

        // ── Sheet 3: Instructions ──
        $this->buildInstructionsSheet($spreadsheet);

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
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($sheet, 1, 'D');

        $widths = ['A' => 30, 'B' => 30, 'C' => 28, 'D' => 22];
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
            'K' => 'Google Location URL',
        ];

        foreach ($branchColumns as $col => $label) {
            $branchSheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($branchSheet, 1, 'K');

        $branchWidths = ['A' => 30, 'B' => 28, 'C' => 28, 'D' => 36, 'E' => 36, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 14, 'J' => 14, 'K' => 40];
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

    public function zipExample(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $this->buildExampleSheet($spreadsheet);
        $this->buildInstructionsSheet($spreadsheet);

        $tmpDir = storage_path('app/facility-migration/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $xlsxPath = $tmpDir.'/facility_import_example_'.now()->format('Y-m-d_His').'.xlsx';
        $zipPath = $tmpDir.'/facility_import_example_'.now()->format('Y-m-d_His').'.zip';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($xlsxPath, basename($xlsxPath));
        $zip->close();

        $filename = 'facility_import_example_'.now()->format('Y-m-d').'.zip';

        return response()->stream(function () use ($zipPath, $xlsxPath) {
            $out = fopen('php://output', 'wb');
            $in = fopen($zipPath, 'rb');
            while (! feof($in)) {
                fwrite($out, fread($in, 8192));
            }
            fclose($in);
            fclose($out);
            @unlink($zipPath);
            @unlink($xlsxPath);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) filesize($zipPath),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function zipBlank(Request $request): StreamedResponse
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
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($sheet, 1, 'D');

        $widths = ['A' => 30, 'B' => 30, 'C' => 28, 'D' => 22];
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
            'K' => 'Google Location URL',
        ];

        foreach ($branchColumns as $col => $label) {
            $branchSheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($branchSheet, 1, 'K');

        $branchWidths = ['A' => 30, 'B' => 28, 'C' => 28, 'D' => 36, 'E' => 36, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 14, 'J' => 14, 'K' => 40];
        foreach ($branchWidths as $col => $w) {
            $branchSheet->getColumnDimension($col)->setWidth($w);
        }

        $tmpDir = storage_path('app/facility-migration/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $xlsxPath = $tmpDir.'/facility_import_template_'.now()->format('Y-m-d_His').'.xlsx';
        $zipPath = $tmpDir.'/facility_import_template_'.now()->format('Y-m-d_His').'.zip';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($xlsxPath, basename($xlsxPath));
        $zip->close();

        $filename = 'facility_import_template_'.now()->format('Y-m-d').'.zip';

        return response()->stream(function () use ($zipPath, $xlsxPath) {
            $out = fopen('php://output', 'wb');
            $in = fopen($zipPath, 'rb');
            while (! feof($in)) {
                fwrite($out, fread($in, 8192));
            }
            fclose($in);
            fclose($out);
            @unlink($zipPath);
            @unlink($xlsxPath);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) filesize($zipPath),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');

        $sheet->setCellValue('A1', 'FACILITY IMPORT — INSTRUCTIONS');
        $sheet->mergeCells('A1:H1');
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
            'LOOKUP FIELDS — You can use the ID, English name, Arabic name, or slug for any lookup field:',
            '  Facility type: { "id": 1 } or { "name": { "en": "Clinic" } } or { "slug": "clinic" }',
            '  Governorate:   { "id": 5 } or { "name": { "en": "Cairo" } } or { "slug": "cairo" }',
            '  City:          { "id": 12 } or { "name": { "en": "Nasr City" } } or { "slug": "nasr-city" }',
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
            '  Must match an existing facility type by ID, name (EN or AR), or slug.',
            '  See "FACILITY TYPES" table below for all available types.',
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
            '  Must match existing names (ID, EN, AR, or slug).',
            '',
            'Latitude / Longitude',
            '  Decimal coordinates.',
            '',
            'Google Location URL',
            '  Google Maps share link for the branch location. Optional.',
            '  Example: "https://maps.app.goo.gl/abc123"',
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
        $headers = ['GENERAL', 'LOOKUP FIELDS — You can use the ID, English name, Arabic name, or slug for any lookup field:', 'FACILITIES SHEET — COLUMN GUIDE', 'BRANCHES SHEET — COLUMN GUIDE', 'IMPORTANT NOTES'];
        foreach ($instructions as $i => $line) {
            $r = $i + 2;
            if (in_array($line, $headers, true)) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']],
                ]);
            }
            if (preg_match('/^(Name|Slug|Facility Type|Governorate|City|Latitude|Longitude|Google Location URL|Facility Name|Branch Name|Phone|Address|IMPORTANT NOTES)$/i', trim($line))) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '4F46E5']],
                ]);
            }
        }

        // ── Reference tables ──────────────────────────────────────────────
        $tableStartRow = $row + 2;
        $colId = 'A';
        $colNameEn = 'B';
        $colNameAr = 'C';
        $colSlug = 'D';

        // ── Facility Types table ──
        $tableStartRow = $this->buildReferenceTable(
            $sheet, $tableStartRow, 'FACILITY TYPES',
            FacilityType::orderBy('id')->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name_en' => $t->getTranslation('name', 'en') ?? '',
                    'name_ar' => $t->getTranslation('name', 'ar') ?? '',
                    'slug' => $t->slug ?? '',
                ])->all()
        );

        $tableStartRow += 1;

        // ── Governorates table ──
        $tableStartRow = $this->buildReferenceTable(
            $sheet, $tableStartRow, 'GOVERNORATES',
            Governorate::orderBy('id')->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name_en' => $g->getTranslation('name', 'en') ?? '',
                    'name_ar' => $g->getTranslation('name', 'ar') ?? '',
                    'slug' => $g->slug ?? '',
                ])->all()
        );

        $tableStartRow += 1;

        // ── Cities table ──
        $cities = City::with('governorate')->orderBy('id')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name_en' => $c->getTranslation('name', 'en') ?? '',
                'name_ar' => $c->getTranslation('name', 'ar') ?? '',
                'slug' => $c->slug ?? '',
                'governorate' => $c->governorate ? $c->governorate->getTranslation('name', 'en') ?? '' : '',
            ])->all();

        $tableStartRow = $this->buildCitiesTable($sheet, $tableStartRow, 'CITIES', $cities);
    }

    /**
     * Build a 4-column reference table (ID | Name EN | Name AR | Slug).
     */
    private function buildReferenceTable(Worksheet $sheet, int $startRow, string $title, array $rows): int
    {
        // Title row
        $sheet->mergeCells("A{$startRow}:D{$startRow}");
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $startRow++;

        // Header row
        $headers = ['ID', 'Name (EN)', 'Name (AR)', 'Slug'];
        foreach ($headers as $col => $label) {
            $colLetter = chr(ord('A') + $col);
            $sheet->setCellValue("{$colLetter}{$startRow}", $label);
        }
        $this->styleHeaderRow($sheet, $startRow, 'D');
        $startRow++;

        // Data rows
        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$startRow}", $row['id']);
            $sheet->setCellValue("B{$startRow}", $row['name_en']);
            $sheet->setCellValue("C{$startRow}", $row['name_ar']);
            $sheet->setCellValue("D{$startRow}", $row['slug']);

            $stripe = ($i % 2 === 0) ? 'F0F9FF' : 'FFFFFF';
            $sheet->getStyle("A{$startRow}:D{$startRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $startRow++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(28);

        return $startRow;
    }

    /**
     * Build a 5-column cities table (ID | Name EN | Name AR | Slug | Governorate).
     */
    private function buildCitiesTable(Worksheet $sheet, int $startRow, string $title, array $rows): int
    {
        // Title row
        $sheet->mergeCells("A{$startRow}:E{$startRow}");
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $startRow++;

        // Header row
        $headers = ['ID', 'Name (EN)', 'Name (AR)', 'Slug', 'Governorate'];
        foreach ($headers as $col => $label) {
            $colLetter = chr(ord('A') + $col);
            $sheet->setCellValue("{$colLetter}{$startRow}", $label);
        }
        $this->styleHeaderRow($sheet, $startRow, 'E');
        $startRow++;

        // Data rows
        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$startRow}", $row['id']);
            $sheet->setCellValue("B{$startRow}", $row['name_en']);
            $sheet->setCellValue("C{$startRow}", $row['name_ar']);
            $sheet->setCellValue("D{$startRow}", $row['slug']);
            $sheet->setCellValue("E{$startRow}", $row['governorate']);

            $stripe = ($i % 2 === 0) ? 'F0F9FF' : 'FFFFFF';
            $sheet->getStyle("A{$startRow}:E{$startRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $startRow++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(30);

        return $startRow;
    }

    private function buildExampleSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');

        $columns = [
            'A' => 'Name',
            'B' => 'Name (AR)',
            'C' => 'Slug',
            'D' => 'Facility Type',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($sheet, 1, 'D');

        $examples = [
            ['El Gouna Medical Center', 'مركز الغردقة الطبي', 'el-gouna-medical-center', 'Clinic'],
            ['Cairo Dental Clinic', 'عيادة أسنان القاهرة', 'cairo-dental-clinic', 'Dental Clinic'],
            ['Alexandria Eye Hospital', 'مستشفى العيون بالإسكندرية', 'alexandria-eye-hospital', 'Hospital'],
        ];

        foreach ($examples as $i => $row) {
            $r = $i + 2;
            foreach ($row as $j => $value) {
                $col = chr(ord('A') + $j);
                $sheet->setCellValue("{$col}{$r}", $value);
            }
            $stripe = ($i % 2 === 0) ? 'F0FDF4' : 'FFFFFF';
            $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(22);
        }

        // Branch examples sheet
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
            'K' => 'Google Location URL',
        ];

        foreach ($branchColumns as $col => $label) {
            $branchSheet->setCellValue("{$col}1", $label);
        }
        $this->styleHeaderRow($branchSheet, 1, 'K');

        $branchExamples = [
            ['El Gouna Medical Center', 'El Gouna Branch', 'فرع الغردقة', 'Abu Tig Marina, El Gouna', 'مارينا أبو تيج، الغردقة', '+20 65 358 0123', 'Red Sea', 'El Gouna', 27.3977, 33.6596, 'https://maps.app.goo.gl/example1'],
            ['El Gouna Medical Center', 'Soma Bay Branch', 'فرع سوما باي', 'Soma Bay Resort', 'منتجع سوما باي', '+20 65 358 0456', 'Red Sea', 'Soma Bay', 27.1044, 33.8350, 'https://maps.app.goo.gl/example2'],
            ['Cairo Dental Clinic', 'Main Branch', 'الفرع الرئيسي', '15 Abbas El Akkad St, Nasr City', 'شارع Abbas El Akkad، مدينة نصر', '+20 2 2273 0000', 'Cairo', 'Nasr City', 30.0561, 31.3389, 'https://maps.app.goo.gl/example3'],
        ];

        foreach ($branchExamples as $i => $row) {
            $r = $i + 2;
            foreach ($row as $j => $value) {
                $col = chr(ord('A') + $j);
                $branchSheet->setCellValue("{$col}{$r}", $value);
            }
            $stripe = ($i % 2 === 0) ? 'F0FDF4' : 'FFFFFF';
            $branchSheet->getStyle("A{$r}:K{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $branchSheet->getRowDimension($r)->setRowHeight(22);
        }

        $widths = ['A' => 30, 'B' => 30, 'C' => 28, 'D' => 22, 'E' => 14, 'F' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $branchWidths = ['A' => 30, 'B' => 22, 'C' => 22, 'D' => 36, 'E' => 36, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 14, 'J' => 14, 'K' => 40];
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
