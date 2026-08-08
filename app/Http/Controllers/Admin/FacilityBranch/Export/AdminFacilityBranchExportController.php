<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Export;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminFacilityBranchExportController extends BaseController
{
    private const MIN_CHUNK_SIZE = 1;
    private const MAX_CHUNK_SIZE = 10000;

    public function __invoke(Request $request): StreamedResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'facility_id' => $request->filled('facility_id') ? (int) $request->input('facility_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
        ];

        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $branches = FacilityBranch::query()
            ->with(['facility', 'governorate', 'city', 'creator:id,name,email'])
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $needle = '%' . $filters['search'] . '%';
                $q->where(function ($w) use ($needle) {
                    $w->where('name->en', 'like', $needle)
                      ->orWhere('name->ar', 'like', $needle)
                      ->orWhere('slug', 'like', $needle);
                });
            })
            ->when($filters['facility_id'] !== null, fn($q) => $q->where('facility_id', $filters['facility_id']))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->latest()
            ->get();

        $facilityName = $filters['facility_id'] !== null
            ? (Facility::find($filters['facility_id'])?->getTranslation('name', 'en') ?? "#{$filters['facility_id']}")
            : 'All facilities';

        $timestamp = now()->format('Y-m-d_His');

        if ($chunkSize === 0 || $branches->count() <= $chunkSize) {
            $spreadsheet = $this->buildSpreadsheet($branches, $facilityName, $filters);
            $filename = 'facility_branches_export_' . $timestamp . '.xlsx';
            return $this->streamXlsx($spreadsheet, $filename);
        }

        $chunks = $branches->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir() . '/branches_export_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = "Part {$partNumber} of {$totalParts}";
            $partSpreadsheet = $this->buildSpreadsheet($chunk, $facilityName, $filters, $partLabel);
            $partFilename = sprintf('branches_part_%02d_of_%02d.xlsx', $partNumber, $totalParts);
            $partPath = $tmpDir . '/' . $partFilename;
            (IOFactory::createWriter($partSpreadsheet, 'Xlsx'))->save($partPath);
            $partSpreadsheet->disconnectWorksheets();
            unset($partSpreadsheet);
            $partFiles[] = ['path' => $partPath, 'name' => $partFilename];
        }

        $zipName = sprintf('facility_branches_export_%s_split_%d.zip', $timestamp, $chunkSize);
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
        Collection $branches,
        string $facilityName,
        array $filters,
        ?string $partLabel = null
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Branches');

        $title = $partLabel ? "FACILITY BRANCHES EXPORT — {$partLabel}" : 'FACILITY BRANCHES EXPORT';
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:N1');
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', 'Generated at:');
        $sheet->setCellValue('B2', now()->format('D, d M Y H:i'));
        $sheet->setCellValue('A3', 'Total rows:');
        $sheet->setCellValue('B3', $branches->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        $sheet->setCellValue('A5', 'FILTERS APPLIED');
        $sheet->mergeCells('A5:N5');
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $filterRows = [
            ['Search', $filters['search'] !== '' ? $filters['search'] : '—'],
            ['Facility', $facilityName],
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

        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", 'BRANCHES');
        $sheet->mergeCells("A{$headerRow}:N{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        $columns = [
            'A' => '#', 'B' => 'Facility name', 'C' => 'Facility slug',
            'D' => 'Branch name', 'E' => 'Branch name (AR)',
            'F' => 'Address', 'G' => 'Address (AR)', 'H' => 'Phone',
            'I' => 'Governorate', 'J' => 'City',
            'K' => 'Latitude', 'L' => 'Longitude',
            'M' => 'Created at', 'N' => 'Creator',
        ];
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$columnHeaderRow}", $label);
        }
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("A{$columnHeaderRow}:N{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataStart = $columnHeaderRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;
        foreach ($branches as $branch) {
            $rowIndex++;
            $phone = $branch->phone;
            if (is_array($phone)) {
                $phone = implode(', ', $phone);
            }
            $creator = $branch->creator;
            $creatorCell = $creator
                ? trim($creator->name . ($creator->email ? " <{$creator->email}>" : ''))
                : '';

            $sheet->setCellValue("A{$dataRow}", $rowIndex);
            $sheet->setCellValue("B{$dataRow}", (string) ($branch->facility?->getTranslation('name', 'en') ?: ''));
            $sheet->setCellValue("C{$dataRow}", (string) ($branch->facility?->slug ?? ''));
            $sheet->setCellValue("D{$dataRow}", (string) ($branch->getTranslation('name', 'en') ?: ''));
            $sheet->setCellValue("E{$dataRow}", (string) ($branch->getTranslation('name', 'ar') ?: ''));
            $sheet->setCellValue("F{$dataRow}", (string) ($branch->getTranslation('address', 'en') ?: ''));
            $sheet->setCellValue("G{$dataRow}", (string) ($branch->getTranslation('address', 'ar') ?: ''));
            $sheet->setCellValue("H{$dataRow}", (string) ($phone ?? ''));
            $sheet->setCellValue("I{$dataRow}", (string) ($branch->governorate?->getTranslation('name', 'en') ?: ''));
            $sheet->setCellValue("J{$dataRow}", (string) ($branch->city?->getTranslation('name', 'en') ?: ''));
            $sheet->setCellValue("K{$dataRow}", $branch->latitude !== null ? (string) $branch->latitude : '');
            $sheet->setCellValue("L{$dataRow}", $branch->longitude !== null ? (string) $branch->longitude : '');
            $sheet->setCellValue("M{$dataRow}", $branch->created_at?->format('d M Y H:i') ?? '');
            $sheet->setCellValue("N{$dataRow}", $creatorCell);

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:N{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$dataRow}:N{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        $widths = [
            'A' => 8, 'B' => 32, 'C' => 28, 'D' => 28, 'E' => 28,
            'F' => 40, 'G' => 40, 'H' => 24, 'I' => 22, 'J' => 22,
            'K' => 14, 'L' => 14, 'M' => 22, 'N' => 32,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", 'END OF REPORT — ' . $branches->count() . ' branch(es) exported');
        $sheet->mergeCells("A{$footerRow}:N{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        return $spreadsheet;
    }
}
