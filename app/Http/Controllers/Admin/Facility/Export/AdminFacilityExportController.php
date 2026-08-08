<?php

namespace App\Http\Controllers\Admin\Facility\Export;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminFacilityExportController extends BaseController
{
    private const MIN_CHUNK_SIZE = 1;
    private const MAX_CHUNK_SIZE = 10000;

    public function __invoke(Request $request): StreamedResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->filled('facility_type_id') ? (int) $request->input('facility_type_id') : null,
            'governorate_id' => $request->filled('governorate_id') ? (int) $request->input('governorate_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
        ];
        $includeBranches = $request->boolean('include_branches');

        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $facilities = Facility::query()
            ->with(['facilityType', 'creator:id,name,email'])
            ->withCount('branches')
            ->when($includeBranches, fn($q) => $q->with(['branches' => fn($bq) => $bq->with(['governorate', 'city'])->orderBy('created_at')]))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($w) use ($filters) {
                    $needle = '%' . $filters['search'] . '%';
                    $w->where('name->en', 'like', $needle)
                      ->orWhere('name->ar', 'like', $needle)
                      ->orWhere('slug', 'like', $needle);
                });
            })
            ->when($filters['facility_type_id'] !== null, fn($q) => $q->where('facility_type_id', $filters['facility_type_id']))
            ->when($filters['governorate_id'] !== null, fn($q) => $q->where('governorate_id', $filters['governorate_id']))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->latest()
            ->get();

        $typeName = $filters['facility_type_id'] !== null
            ? (FacilityType::find($filters['facility_type_id'])?->getTranslation('name', 'en') ?? "#{$filters['facility_type_id']}")
            : 'All types';
        $govName = $filters['governorate_id'] !== null
            ? (Governorate::find($filters['governorate_id'])?->getTranslation('name', 'en') ?? "#{$filters['governorate_id']}")
            : 'All governorates';

        $timestamp = now()->format('Y-m-d_His');

        if ($chunkSize === 0 || $facilities->count() <= $chunkSize) {
            $spreadsheet = $this->buildSpreadsheet($facilities, $typeName, $govName, $filters, $includeBranches);
            $filename = ($includeBranches ? 'facilities_with_branches_' : 'facilities_export_') . $timestamp . '.xlsx';
            return $this->streamXlsx($spreadsheet, $filename);
        }

        $chunks = $facilities->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir() . '/facilities_export_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = "Part {$partNumber} of {$totalParts}";
            $partSpreadsheet = $this->buildSpreadsheet($chunk, $typeName, $govName, $filters, $includeBranches, $partLabel);
            $partFilename = sprintf(
                '%sfacilities_part_%02d_of_%02d.xlsx',
                $includeBranches ? 'with_branches_' : '',
                $partNumber,
                $totalParts
            );
            $partPath = $tmpDir . '/' . $partFilename;
            (IOFactory::createWriter($partSpreadsheet, 'Xlsx'))->save($partPath);
            $partSpreadsheet->disconnectWorksheets();
            unset($partSpreadsheet);
            $partFiles[] = ['path' => $partPath, 'name' => $partFilename];
        }

        $zipName = sprintf(
            '%sfacilities_export_%s_split_%d.zip',
            $includeBranches ? 'with_branches_' : '',
            $timestamp,
            $chunkSize
        );
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

    private function buildSpreadsheet(
        Collection $facilities,
        string $typeName,
        string $govName,
        array $filters,
        bool $includeBranches,
        ?string $partLabel = null
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');

        // ------ Title block ------
        $title = $partLabel ? "FACILITIES EXPORT — {$partLabel}" : 'FACILITIES EXPORT';
        $sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', 'Generated at:');
        $sheet->setCellValue('B2', now()->format('D, d M Y H:i'));
        $sheet->setCellValue('A3', 'Total rows:');
        $sheet->setCellValue('B3', $facilities->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Filter block ------
        $sheet->setCellValue('A5', 'FILTERS APPLIED');
        $sheet->mergeCells('A5:I5');
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $filterRows = [
            ['Search', $filters['search'] !== '' ? $filters['search'] : '—'],
            ['Facility type', $typeName],
            ['Governorate', $govName],
            ['Created from', $filters['created_from'] ?: '—'],
            ['Created to', $filters['created_to'] ?: '—'],
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

        // ------ Facilities table ------
        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", 'FACILITIES');
        $sheet->mergeCells("A{$headerRow}:I{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        $columns = [
            'A' => '#', 'B' => 'Name', 'C' => 'Name (AR)', 'D' => 'Slug',
            'E' => 'Facility type',
            'F' => 'Branches', 'G' => 'Created at', 'H' => 'Updated at',
            'I' => 'Creator',
        ];
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$columnHeaderRow}", $label);
        }
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("A{$columnHeaderRow}:I{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataStart = $columnHeaderRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;
        foreach ($facilities as $facility) {
            $rowIndex++;
            $nameEn = (string) ($facility->getTranslation('name', 'en') ?: '');
            $nameAr = (string) ($facility->getTranslation('name', 'ar') ?: '');
            $typeLabel = (string) ($facility->facilityType?->getTranslation('name', 'en') ?: '');

            $creator = $facility->creator;
            $creatorCell = $creator
                ? trim($creator->name . ($creator->email ? " <{$creator->email}>" : ''))
                : '';

            $sheet->setCellValue("A{$dataRow}", $rowIndex);
            $sheet->setCellValue("B{$dataRow}", $nameEn);
            $sheet->setCellValue("C{$dataRow}", $nameAr);
            $sheet->setCellValue("D{$dataRow}", (string) $facility->slug);
            $sheet->setCellValue("E{$dataRow}", $typeLabel);
            $sheet->setCellValue("F{$dataRow}", $facility->branches_count ?? 0);
            $sheet->setCellValue("G{$dataRow}", $facility->created_at?->format('d M Y H:i') ?? '');
            $sheet->setCellValue("H{$dataRow}", $facility->updated_at?->format('d M Y H:i') ?? '');
            $sheet->setCellValue("I{$dataRow}", $creatorCell);

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:I{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$dataRow}:I{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        $widths = [
            'A' => 8, 'B' => 36, 'C' => 36, 'D' => 30, 'E' => 22,
            'F' => 12, 'G' => 22, 'H' => 22, 'I' => 32,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", 'END OF REPORT — ' . $facilities->count() . ' facility(ies) exported');
        $sheet->mergeCells("A{$footerRow}:I{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($includeBranches) {
            $this->buildBranchSheet($spreadsheet, $facilities);
        }

        return $spreadsheet;
    }

    private function buildBranchSheet(Spreadsheet $spreadsheet, Collection $facilities): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Branches');

        $sheet->setCellValue('A1', 'BRANCHES');
        $sheet->mergeCells('A1:L1');
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
        ]);

        $headerRow = 3;
        $columns = [
            'A' => '#', 'B' => 'Facility name', 'C' => 'Facility slug',
            'D' => 'Branch name', 'E' => 'Branch name (AR)',
            'F' => 'Address', 'G' => 'Address (AR)', 'H' => 'Phone',
            'I' => 'Governorate', 'J' => 'City',
            'K' => 'Latitude', 'L' => 'Longitude',
        ];
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $row = $headerRow + 1;
        $index = 0;
        foreach ($facilities as $facility) {
            if (!$facility->relationLoaded('branches')) {
                continue;
            }
            foreach ($facility->branches as $branch) {
                $index++;
                $phone = $branch->phone;
                if (is_array($phone)) {
                    $phone = implode(', ', $phone);
                }
                $sheet->setCellValue("A{$row}", $index);
                $sheet->setCellValue("B{$row}", (string) ($facility->getTranslation('name', 'en') ?: ''));
                $sheet->setCellValue("C{$row}", (string) $facility->slug);
                $sheet->setCellValue("D{$row}", (string) ($branch->getTranslation('name', 'en') ?: ''));
                $sheet->setCellValue("E{$row}", (string) ($branch->getTranslation('name', 'ar') ?: ''));
                $sheet->setCellValue("F{$row}", (string) ($branch->getTranslation('address', 'en') ?: ''));
                $sheet->setCellValue("G{$row}", (string) ($branch->getTranslation('address', 'ar') ?: ''));
                $sheet->setCellValue("H{$row}", (string) ($phone ?? ''));
                $sheet->setCellValue("I{$row}", (string) ($branch->governorate?->getTranslation('name', 'en') ?: ''));
                $sheet->setCellValue("J{$row}", (string) ($branch->city?->getTranslation('name', 'en') ?: ''));
                $sheet->setCellValue("K{$row}", $branch->latitude !== null ? (string) $branch->latitude : '');
                $sheet->setCellValue("L{$row}", $branch->longitude !== null ? (string) $branch->longitude : '');

                $stripe = ($index % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;
            }
        }

        $widths = [
            'A' => 8, 'B' => 32, 'C' => 28, 'D' => 28, 'E' => 28,
            'F' => 40, 'G' => 40, 'H' => 24, 'I' => 22, 'J' => 22,
            'K' => 14, 'L' => 14,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $footerRow = $row + 1;
        $sheet->setCellValue("A{$footerRow}", 'END OF REPORT — ' . $index . ' branch(es) exported');
        $sheet->mergeCells("A{$footerRow}:L{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
