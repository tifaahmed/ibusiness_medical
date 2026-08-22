<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Admin\Facility\Migration\Concerns\LookupOptions;
use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminFacilityMigrationTemplateController extends BaseController
{
    use LookupOptions;

    /**
     * The Facilities sheet, left to right. The sales rep and the discount sit
     * next to the type because they are facility-level columns — a branch has
     * neither.
     */
    private const FACILITY_COLUMNS = [
        'A' => ['label' => 'Name', 'width' => 30],
        'B' => ['label' => 'Name (AR)', 'width' => 30],
        'C' => ['label' => 'Slug', 'width' => 28],
        'D' => ['label' => 'Facility Type', 'width' => 22],
        'E' => ['label' => 'Sales', 'width' => 28],
        'F' => ['label' => 'Discount %', 'width' => 14],
    ];

    private const BRANCH_COLUMNS = [
        'A' => ['label' => 'Facility Name', 'width' => 30],
        'B' => ['label' => 'Branch Name', 'width' => 28],
        'C' => ['label' => 'Branch Name (AR)', 'width' => 28],
        'D' => ['label' => 'Address', 'width' => 36],
        'E' => ['label' => 'Address (AR)', 'width' => 36],
        'F' => ['label' => 'Phone', 'width' => 22],
        'G' => ['label' => 'Governorate', 'width' => 22],
        'H' => ['label' => 'City', 'width' => 22],
        'I' => ['label' => 'Latitude', 'width' => 14],
        'J' => ['label' => 'Longitude', 'width' => 14],
        'K' => ['label' => 'Google Location URL', 'width' => 40],
    ];

    /**
     * The Managers sheet — a facility's contact people, tied to it by name the
     * same way a branch is.
     */
    private const MANAGER_COLUMNS = [
        'A' => ['label' => 'Facility Name', 'width' => 30],
        'B' => ['label' => 'Manager Name', 'width' => 28],
        'C' => ['label' => 'Position', 'width' => 26],
        'D' => ['label' => 'Phones', 'width' => 34],
    ];

    public function example(Request $request): StreamedResponse
    {
        return $this->streamXlsx(
            $this->workbook(withExamples: true),
            'facility_import_example_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function blank(Request $request): StreamedResponse
    {
        return $this->streamXlsx(
            $this->workbook(withExamples: false),
            'facility_import_template_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function zipExample(Request $request): StreamedResponse
    {
        return $this->streamZip(
            $this->workbook(withExamples: true),
            'facility_import_example_'.now()->format('Y-m-d'),
            withInstructions: true
        );
    }

    public function zipBlank(Request $request): StreamedResponse
    {
        return $this->streamZip(
            $this->workbook(withExamples: false),
            'facility_import_template_'.now()->format('Y-m-d'),
            withInstructions: false
        );
    }

    /**
     * The workbook both downloads share: the two sheets that are imported, plus
     * — for the example — the instructions and the reference tables listing what
     * this site already holds, sales reps included.
     */
    private function workbook(bool $withExamples): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');
        $this->buildSheet($sheet, self::FACILITY_COLUMNS, $withExamples ? $this->facilityExamples() : []);

        $branchSheet = $spreadsheet->createSheet();
        $branchSheet->setTitle('Branches');
        $this->buildSheet($branchSheet, self::BRANCH_COLUMNS, $withExamples ? $this->branchExamples() : []);

        $managerSheet = $spreadsheet->createSheet();
        $managerSheet->setTitle('Managers');
        $this->buildSheet($managerSheet, self::MANAGER_COLUMNS, $withExamples ? $this->managerExamples() : []);

        // The empty template is meant to be filled in a hurry, but a Sales cell
        // still has to name somebody this site knows — so both downloads carry
        // the reference tables.
        $this->buildInstructionsSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Header row, column widths and — when there are any — striped example rows.
     *
     * @param  array<string, array{label: string, width: int}>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function buildSheet(Worksheet $sheet, array $columns, array $rows): void
    {
        $lastCol = array_key_last($columns);

        foreach ($columns as $col => $spec) {
            $sheet->setCellValue("{$col}1", $spec['label']);
            $sheet->getColumnDimension($col)->setWidth($spec['width']);
        }
        $this->styleHeaderRow($sheet, 1, $lastCol);

        foreach ($rows as $i => $row) {
            $r = $i + 2;
            foreach (array_values($row) as $j => $value) {
                $sheet->setCellValue(chr(ord('A') + $j).$r, $value);
            }
            $stripe = ($i % 2 === 0) ? 'F0FDF4' : 'FFFFFF';
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(22);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function facilityExamples(): array
    {
        // A made-up sales name would be created as a new person on import, so
        // the example borrows real ones where this site has any — the row can
        // then be imported as it stands.
        $reps = $this->salesRows()->take(3)->map(fn (Sales $s) => $this->salesLabel($s))->values()->all();
        $rep = fn (int $i, string $fallback) => $reps[$i] ?? $fallback;

        return [
            ['El Gouna Medical Center', 'مركز الغردقة الطبي', 'el-gouna-medical-center', 'Clinic', $rep(0, 'Ahmed Hassan'), 15],
            ['Cairo Dental Clinic', 'عيادة أسنان القاهرة', 'cairo-dental-clinic', 'Dental Clinic', $rep(1, 'Mona Adel'), 20],
            ['Alexandria Eye Hospital', 'مستشفى العيون بالإسكندرية', 'alexandria-eye-hospital', 'Hospital', $rep(2, 'Ahmed Hassan'), 10],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function branchExamples(): array
    {
        return [
            ['El Gouna Medical Center', 'El Gouna Branch', 'فرع الغردقة', 'Abu Tig Marina, El Gouna', 'مارينا أبو تيج، الغردقة', '+20 65 358 0123', 'Red Sea', 'El Gouna', 27.3977, 33.6596, 'https://maps.app.goo.gl/example1'],
            ['El Gouna Medical Center', 'Soma Bay Branch', 'فرع سوما باي', 'Soma Bay Resort', 'منتجع سوما باي', '+20 65 358 0456', 'Red Sea', 'Soma Bay', 27.1044, 33.8350, 'https://maps.app.goo.gl/example2'],
            ['Cairo Dental Clinic', 'Main Branch', 'الفرع الرئيسي', '15 Abbas El Akkad St, Nasr City', 'شارع عباس العقاد، مدينة نصر', '+20 2 2273 0000', 'Cairo', 'Nasr City', 30.0561, 31.3389, 'https://maps.app.goo.gl/example3'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function managerExamples(): array
    {
        return [
            ['El Gouna Medical Center', 'Dr. Sameh Farid', 'General Manager', "+20 100 111 2233\n+20 65 358 0123"],
            ['El Gouna Medical Center', 'Nada Sherif', 'Reception Supervisor', '+20 100 444 5566'],
            ['Cairo Dental Clinic', 'Dr. Karim Fouad', 'Owner', '+20 122 777 8899'],
        ];
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
     * The same workbook, zipped — for mail servers and admin panels that will
     * not carry a bare .xlsx.
     */
    private function streamZip(Spreadsheet $spreadsheet, string $basename, bool $withInstructions): StreamedResponse
    {
        $tmpDir = storage_path('app/facility-migration/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $xlsxPath = $tmpDir.'/'.$basename.'_'.$stamp.'.xlsx';
        $zipPath = $tmpDir.'/'.$basename.'_'.$stamp.'.zip';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($xlsxPath, basename($xlsxPath));
        $zip->addFromString('README.txt', $this->zipReadme($withInstructions));
        $zip->close();

        $filename = $basename.'.zip';
        $size = (string) filesize($zipPath);

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
            'Content-Length' => $size,
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function zipReadme(bool $withExamples): string
    {
        $what = $withExamples
            ? 'filled-in example rows you can edit or delete'
            : 'empty columns ready to fill in';

        return implode("\n", [
            'FACILITY IMPORT PACKAGE',
            '',
            'This zip holds one .xlsx workbook with '.$what.'.',
            '',
            'Sheets:',
            '  Facilities   — Name, Name (AR), Slug, Facility Type, Sales, Discount %',
            '  Branches     — one row per branch, tied to a facility by its Name',
            '  Managers     — one row per contact person, tied the same way',
            '  Instructions — the column guide, plus the facility types, governorates,',
            '                 cities and SALES REPS this site already has.',
            '',
            'Upload this zip (or the .xlsx inside it) on Facilities → Migration → Import.',
            'The preview screen lets you correct every value, pick a different sales rep,',
            'and create a governorate, city or sales rep the sheet names but this site',
            'does not have yet — without leaving the page.',
            '',
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
            '• This file has three sheets that are imported: "Facilities", "Branches" and "Managers".',
            '• Fill in the "Facilities" sheet first, then the "Branches" and "Managers" sheets.',
            '• The "Facilities" sheet requires at minimum: Name, Name (AR), and Facility Type.',
            '• The "Branches" sheet is optional — only add rows if the facility has physical branches.',
            '• The "Managers" sheet is optional — one row per contact person at the facility.',
            '',
            'LOOKUP FIELDS — You can use the ID, English name, Arabic name, or slug for any lookup field:',
            '  Facility type: { "id": 1 } or { "name": { "en": "Clinic" } } or { "slug": "clinic" }',
            '  Governorate:   { "id": 5 } or { "name": { "en": "Cairo" } } or { "slug": "cairo" }',
            '  City:          { "id": 12 } or { "name": { "en": "Nasr City" } } or { "slug": "nasr-city" }',
            '  Sales:         the rep\'s name as written in the SALES REPS table below.',
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
            'Sales',
            '  The sales rep who owns this facility. Optional.',
            '  Write the name exactly as it appears in the "SALES REPS" table below —',
            '  matching ignores case and Arabic letter shapes (هـ/ة, ي/ى, أ/ا are the same).',
            '  A name that matches nobody is offered in the import preview as "(new)":',
            '  you can pick an existing rep instead, or press "Create it" to add the rep',
            '  to this site there and then, without leaving the page.',
            '  Leave the cell empty to leave the facility without a sales rep.',
            '  Example: "Ahmed Hassan"',
            '',
            'Discount %',
            '  The facility-wide discount, as a number between 0 and 100.',
            '  The "%" sign is optional — "15", "15%" and "15 %" all mean fifteen percent.',
            '  Leave the cell empty for no discount.',
            '  Example: 15',
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
            '  A governorate or city this site does not have yet can be created straight',
            '  from the import preview, the same way a sales rep can.',
            '',
            'Latitude / Longitude',
            '  Decimal coordinates.',
            '',
            'Google Location URL',
            '  Google Maps share link for the branch location. Optional.',
            '  Example: "https://maps.app.goo.gl/abc123"',
            '',
            '',
            'MANAGERS SHEET — COLUMN GUIDE',
            '',
            'Facility Name',
            '  Must exactly match the "Name" from the Facilities sheet. Required.',
            '  Add one row per person — repeat the facility name on each of them.',
            '',
            'Manager Name',
            '  The person\'s name. Required — a row without one is skipped.',
            '  Matching ignores case and Arabic letter shapes, so re-importing the same',
            '  sheet updates the person rather than listing them twice.',
            '  Example: "Dr. Sameh Farid"',
            '',
            'Position',
            '  What they do at the facility. Optional.',
            '  Example: "General Manager"',
            '',
            'Phones',
            '  One or more numbers, separated by a comma, a semicolon or a line break.',
            '  Example: "+20 100 111 2233, +20 65 358 0123"',
            '',
            '',
            'IMPORTANT NOTES',
            '• Match names EXACTLY — the import matches by name, so typos create new facilities.',
            '• Duplicate names across different facilities will cause unpredictable matching.',
            '• Branches and managers without a matching Facility Name will be skipped.',
            '• Importing never deletes a branch or a manager for being absent from the',
            '  sheet — remove those on the facility screen.',
            '• Leaving Sales or Discount % out of the sheet entirely keeps whatever the',
            '  facility already has; an empty cell in a column that IS present clears it.',
            '• Preview your import before committing to catch errors early.',
        ];

        $row = 2;
        foreach ($instructions as $line) {
            $sheet->setCellValue("B{$row}", $line);
            $row++;
        }

        // Style section headers
        $headers = [
            'GENERAL',
            'LOOKUP FIELDS — You can use the ID, English name, Arabic name, or slug for any lookup field:',
            'FACILITIES SHEET — COLUMN GUIDE',
            'BRANCHES SHEET — COLUMN GUIDE',
            'MANAGERS SHEET — COLUMN GUIDE',
            'IMPORTANT NOTES',
        ];
        foreach ($instructions as $i => $line) {
            $r = $i + 2;
            if (in_array($line, $headers, true)) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']],
                ]);
            }
            if (preg_match('/^(Name|Slug|Facility Type|Sales|Discount %|Governorate|City|Latitude|Longitude|Google Location URL|Facility Name|Branch Name|Manager Name|Position|Phones?|Address|IMPORTANT NOTES)$/i', trim($line))) {
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '4F46E5']],
                ]);
            }
        }

        // ── Reference tables ──────────────────────────────────────────────
        $tableStartRow = $row + 2;

        // ── Sales reps table ── first: it is the one column of this sheet with
        // no slug to fall back on, so the operator has to read the list.
        $tableStartRow = $this->buildSalesTable($sheet, $tableStartRow, $this->salesReference());

        $tableStartRow += 1;

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

        $this->buildCitiesTable($sheet, $tableStartRow, 'CITIES', $cities);
    }

    /**
     * Every sales rep on this site, with how many facilities each already owns —
     * the count is what tells a real rep apart from a duplicate created by a
     * mistyped cell in an earlier import.
     *
     * @return array<int, array<string, mixed>>
     */
    private function salesReference(): array
    {
        $counts = Facility::query()
            ->selectRaw('sales_id, COUNT(*) as total')
            ->whereNotNull('sales_id')
            ->groupBy('sales_id')
            ->pluck('total', 'sales_id');

        return $this->salesRows()->map(function (Sales $sales) use ($counts) {
            $names = $this->salesTranslations($sales);

            return [
                'id' => $sales->id,
                'name' => $this->salesLabel($sales),
                'name_en' => $names['en'] ?? '',
                'name_ar' => $names['ar'] ?? '',
                'facilities' => (int) ($counts[$sales->id] ?? 0),
            ];
        })->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Sales>
     */
    private function salesRows(): \Illuminate\Database\Eloquent\Collection
    {
        return Sales::orderBy('id')->get();
    }

    /**
     * ID | Name | Name (AR) | Facilities. Written the way the Sales column
     * expects it: copy the "Name" cell into the sheet as it stands.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildSalesTable(Worksheet $sheet, int $startRow, array $rows): int
    {
        $sheet->mergeCells("A{$startRow}:D{$startRow}");
        $sheet->setCellValue("A{$startRow}", 'SALES REPS — write one of these names in the "Sales" column');
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $startRow++;

        foreach (['ID', 'Name', 'Name (AR)', 'Facilities'] as $col => $label) {
            $sheet->setCellValue(chr(ord('A') + $col).$startRow, $label);
        }
        $this->styleHeaderRow($sheet, $startRow, 'D');
        $startRow++;

        if ($rows === []) {
            $sheet->setCellValue("A{$startRow}", '');
            $sheet->setCellValue("B{$startRow}", 'No sales reps on this site yet — write a name in the Sales column and create it from the import preview.');
            $sheet->getStyle("B{$startRow}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            ]);

            return $startRow + 1;
        }

        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$startRow}", $row['id']);
            $sheet->setCellValue("B{$startRow}", $row['name']);
            $sheet->setCellValue("C{$startRow}", $row['name_ar']);
            $sheet->setCellValue("D{$startRow}", $row['facilities']);

            $stripe = ($i % 2 === 0) ? 'EEF2FF' : 'FFFFFF';
            $sheet->getStyle("A{$startRow}:D{$startRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $startRow++;
        }

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(28);

        return $startRow;
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
