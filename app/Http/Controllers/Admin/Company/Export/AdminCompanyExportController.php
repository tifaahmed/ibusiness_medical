<?php

namespace App\Http\Controllers\Admin\Company\Export;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Concerns\ExportsCompanyColumns;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCompanyExportController extends BaseController
{
    use CreatorScoped;
    use ExportsCompanyColumns;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * Export every company the current admin can see (creator scope applied)
     * to a plain XLSX file. The header row is exactly what the import parser
     * understands, so the exported file can be edited and imported straight
     * back — including the id.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $companies = Company::query()
            ->with('creator:id,name,email')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->orderBy('id')
            ->get();

        $spreadsheet = $this->buildSpreadsheet($companies);
        $filename = 'companies_export_' . now()->format('Y-m-d_His') . '.xlsx';

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
     * @param Collection<int, Company> $companies
     */
    private function buildSpreadsheet(Collection $companies): Spreadsheet
    {
        $isRtl = app()->getLocale() === 'ar';
        $defs = $this->companyColumnDefinitions();
        $keys = array_keys($defs);

        $letters = [];
        foreach ($keys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $firstCol = reset($letters) ?: 'A';
        $lastCol = end($letters) ?: 'A';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.company_export.sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // Row 1 is the plain header row — this is what the import parser looks for.
        foreach ($keys as $key) {
            $sheet->setCellValue("{$letters[$key]}1", $defs[$key]['label']);
        }
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle("{$firstCol}1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $dataRow = 2;
        $rowIndex = 0;
        foreach ($companies as $company) {
            $rowIndex++;
            $creator = $company->creator;

            $values = [
                'id'               => (string) $company->id,
                'name_en'          => (string) ($company->getTranslation('name', 'en') ?: ''),
                'name_ar'          => (string) ($company->getTranslation('name', 'ar') ?: ''),
                'slug'             => (string) $company->slug,
                'created_by_email' => (string) ($creator?->email ?? ''),
                'created_at'       => $company->created_at?->format('Y-m-d H:i:s') ?? '',
                'updated_at'       => $company->updated_at?->format('Y-m-d H:i:s') ?? '',
            ];

            foreach ($keys as $key) {
                $sheet->setCellValueExplicit("{$letters[$key]}{$dataRow}", $values[$key], DataType::TYPE_STRING);
            }

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("{$firstCol}{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            foreach ($keys as $key) {
                if (!empty($defs[$key]['align'])) {
                    $sheet->getStyle("{$letters[$key]}{$dataRow}")->getAlignment()->setHorizontal($defs[$key]['align']);
                }
            }
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        foreach ($keys as $key) {
            $sheet->getColumnDimension($letters[$key])->setWidth($defs[$key]['width']);
        }

        $sheet->setSelectedCells('A1');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
