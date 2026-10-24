<?php

namespace App\Http\Controllers\Admin\Store\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Support\StoreSpreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloadable starting points for the store import: a blank workbook with the
 * three sheets and their headers, and an example with a filled-in store, branch
 * and contact. Both use {@see StoreSpreadsheet}, so they always match what the
 * importer reads.
 */
class AdminStoreImportTemplateController extends BaseController
{
    public function blank(): StreamedResponse
    {
        return $this->stream($this->book(false), 'stores_import_template.xlsx');
    }

    public function example(): StreamedResponse
    {
        return $this->stream($this->book(true), 'stores_import_example.xlsx');
    }

    private function book(bool $withExample): Spreadsheet
    {
        $book = new Spreadsheet;

        $stores = [['1', 'Green Pharmacy', 'صيدلية جرين', '', 'Pharmacies', '', '2026-01-15', 'no', '', '', '', '']];
        $branches = [['1', 'Green Pharmacy', '', 'Main branch', 'الفرع الرئيسي', '12 Tahrir St', '١٢ شارع التحرير', '01012345678 (phone), 0226741234 (landline)', 'Cairo', 'Cairo', '30.0444', '31.2357']];
        $managers = [['1', 'Green Pharmacy', '', 'Mona Ali', 'Account manager', '01099999999 (phone)']];

        $this->sheet($book->getActiveSheet(), StoreSpreadsheet::SHEET_STORES, StoreSpreadsheet::storeHeaders(), $withExample ? $stores : [], '0F766E');
        $this->sheet($book->createSheet(), StoreSpreadsheet::SHEET_BRANCHES, StoreSpreadsheet::branchHeaders(), $withExample ? $branches : [], '7C3AED');
        $this->sheet($book->createSheet(), StoreSpreadsheet::SHEET_MANAGERS, StoreSpreadsheet::managerHeaders(), $withExample ? $managers : [], '0284C7');
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function sheet(Worksheet $sheet, string $title, array $headers, array $rows, string $colour): void
    {
        $sheet->setTitle($title);
        foreach ($headers as $c => $label) {
            $sheet->setCellValueExplicit([$c + 1, 1], $label, DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($c + 1)->setWidth(24);
        }
        $last = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colour]],
        ]);
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 2], $value, DataType::TYPE_STRING);
            }
        }
    }

    private function stream(Spreadsheet $book, string $filename): StreamedResponse
    {
        return response()->stream(function () use ($book) {
            IOFactory::createWriter($book, 'Xlsx')->save('php://output');
            $book->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
