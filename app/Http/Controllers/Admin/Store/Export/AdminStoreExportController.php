<?php

namespace App\Http\Controllers\Admin\Store\Export;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use App\Support\StoreSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Stores to XLSX: a Stores sheet, plus optional Branches and Managers sheets.
 * Read-only. The layout comes from {@see StoreSpreadsheet}, which the importer
 * reads with, so the file can be edited and brought back in.
 */
class AdminStoreExportController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    private const MIN_CHUNK_SIZE = 1;

    private const MAX_CHUNK_SIZE = 10000;

    public function __invoke(Request $request): StreamedResponse
    {
        $search = trim((string) $request->input('search', ''));
        $withBranches = $request->boolean('include_branches');
        // Contacts ride along with the branches unless the caller says otherwise.
        $withManagers = $request->has('include_managers') ? $request->boolean('include_managers') : $withBranches;

        $presence = fn (string $key) => in_array($request->input($key), ['with', 'without'], true) ? $request->input($key) : null;
        $categoryIds = collect(explode(',', (string) $request->input('category_ids', '')))
            ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->values()->all();
        $filters = [
            'category_ids' => $categoryIds,
            'sales_id' => $request->filled('sales_id') ? (int) $request->input('sales_id') : null,
            'sales_presence' => $presence('sales_presence'),
            'managers_presence' => $presence('managers_presence'),
            'contract_presence' => $presence('contract_presence'),
            'online_only' => in_array($request->input('online_only'), ['yes', 'no'], true) ? $request->input('online_only') : null,
            'governorate_id' => $request->filled('governorate_id') ? (int) $request->input('governorate_id') : null,
            'city_id' => $request->filled('city_id') ? (int) $request->input('city_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
        ];

        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $allKeys = array_keys(StoreSpreadsheet::storeColumns());
        $rawColumns = trim((string) $request->input('columns', ''));
        $columns = $rawColumns !== ''
            ? array_values(array_intersect($allKeys, array_map('trim', explode(',', $rawColumns))))
            : $allKeys;
        $columns = $columns ?: $allKeys;

        $stores = Store::query()
            ->with(['categories:id,name', 'sales'])
            ->withCount(['branches', 'managers', 'products'])
            ->when($withBranches, fn ($q) => $q->with(['branches' => fn ($b) => $b->with(['governorate', 'city'])->orderBy('id')]))
            ->when($withManagers, fn ($q) => $q->with('managers'))
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title->en', 'like', "%{$search}%")
                        ->orWhere('title->ar', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_ids'] !== [], fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('store_categories.id', $filters['category_ids'])))
            ->when($filters['sales_id'] !== null, fn ($q) => $q->where('sales_id', $filters['sales_id']))
            ->when($filters['sales_presence'] === 'with', fn ($q) => $q->whereNotNull('sales_id'))
            ->when($filters['sales_presence'] === 'without', fn ($q) => $q->whereNull('sales_id'))
            ->when($filters['managers_presence'] === 'with', fn ($q) => $q->whereHas('managers'))
            ->when($filters['managers_presence'] === 'without', fn ($q) => $q->whereDoesntHave('managers'))
            ->when($filters['contract_presence'] === 'with', fn ($q) => $q->whereNotNull('contracted_at'))
            ->when($filters['contract_presence'] === 'without', fn ($q) => $q->whereNull('contracted_at'))
            ->when($filters['online_only'] !== null, fn ($q) => $q->where('online_only', $filters['online_only'] === 'yes'))
            ->when($filters['governorate_id'] !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('governorate_id', $filters['governorate_id'])))
            ->when($filters['city_id'] !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('city_id', $filters['city_id'])))
            ->when($filters['created_from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['created_to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->get();

        $stamp = now()->format('Y-m-d_His');
        $prefix = $withBranches || $withManagers ? 'stores_full_' : 'stores_export_';

        if ($chunkSize === 0 || $stores->count() <= $chunkSize) {
            return $this->streamXlsx($this->book($stores, $columns, $withBranches, $withManagers), $prefix.$stamp.'.xlsx');
        }

        // Too many rows for one file: one workbook per chunk, zipped together.
        $parts = $stores->chunk($chunkSize)->values();
        $tmp = sys_get_temp_dir().'/stores_export_'.uniqid('', true);
        mkdir($tmp, 0700, true);

        $zipName = sprintf('%s%s_split_%d.zip', $prefix, $stamp, $chunkSize);
        $zipPath = $tmp.'/'.$zipName;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($parts as $i => $part) {
            $name = sprintf('stores_part_%02d_of_%02d.xlsx', $i + 1, $parts->count());
            $book = $this->book($part, $columns, $withBranches, $withManagers);
            IOFactory::createWriter($book, 'Xlsx')->save($tmp.'/'.$name);
            $book->disconnectWorksheets();
            $zip->addFile($tmp.'/'.$name, $name);
        }
        $zip->close();

        return response()->stream(function () use ($zipPath, $tmp) {
            readfile($zipPath);
            foreach (glob($tmp.'/*') as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
            'Content-Length' => filesize($zipPath),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** @param list<string> $columns */
    private function book(Collection $stores, array $columns, bool $withBranches, bool $withManagers): Spreadsheet
    {
        $book = new Spreadsheet;
        $this->storesSheet($book->getActiveSheet(), $stores, $columns);
        if ($withBranches) {
            $this->branchesSheet($book->createSheet(), $stores);
        }
        if ($withManagers) {
            $this->managersSheet($book->createSheet(), $stores);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function streamXlsx(Spreadsheet $book, string $filename): StreamedResponse
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

    /** @param list<string> $columns */
    private function storesSheet(Worksheet $sheet, Collection $stores, array $columns): void
    {
        $sheet->setTitle(StoreSpreadsheet::SHEET_STORES);
        $defs = StoreSpreadsheet::storeColumns();

        $rows = [];
        foreach ($stores as $i => $store) {
            $values = [
                'index' => $i + 1,
                'title' => (string) $store->getTranslation('title', 'en', false),
                'title_ar' => (string) $store->getTranslation('title', 'ar', false),
                'slug' => (string) $store->slug,
                'categories' => $store->categories->map(fn ($c) => $c->getTranslation('name', 'en', false) ?: $c->getTranslation('name', 'ar', false))->filter()->implode(', '),
                'sales' => $store->sales?->displayName() ?? '',
                'contracted_at' => $store->contracted_at?->toDateString() ?? '',
                'online_only' => $store->online_only ? 'yes' : 'no',
                'branches' => $store->branches_count,
                'managers' => $store->managers_count,
                'products' => $store->products_count,
                'created_at' => $store->created_at?->format('Y-m-d H:i') ?? '',
            ];
            $rows[] = array_map(fn ($key) => $values[$key], $columns);
        }

        $this->write(
            $sheet,
            array_map(fn ($key) => $defs[$key]['label'], $columns),
            $rows,
            '0F766E',
            array_map(fn ($key) => $defs[$key]['width'], $columns)
        );
    }

    private function branchesSheet(Worksheet $sheet, Collection $stores): void
    {
        $sheet->setTitle(StoreSpreadsheet::SHEET_BRANCHES);

        $rows = [];
        $n = 0;
        foreach ($stores as $store) {
            foreach ($store->branches as $branch) {
                $rows[] = [
                    ++$n,
                    (string) $store->getTranslation('title', 'en', false),
                    (string) $store->slug,
                    (string) $branch->getTranslation('name', 'en', false),
                    (string) $branch->getTranslation('name', 'ar', false),
                    (string) $branch->getTranslation('address', 'en', false),
                    (string) $branch->getTranslation('address', 'ar', false),
                    StoreSpreadsheet::writePhones($branch->phone),
                    (string) ($branch->governorate?->getTranslation('name', 'en', false) ?: ''),
                    (string) ($branch->city?->getTranslation('name', 'en', false) ?: ''),
                    $branch->latitude !== null ? (string) $branch->latitude : '',
                    $branch->longitude !== null ? (string) $branch->longitude : '',
                ];
            }
        }

        $this->write($sheet, StoreSpreadsheet::branchHeaders(), $rows, '7C3AED', [8, 32, 28, 28, 28, 40, 40, 34, 22, 22, 14, 14]);
    }

    private function managersSheet(Worksheet $sheet, Collection $stores): void
    {
        $sheet->setTitle(StoreSpreadsheet::SHEET_MANAGERS);

        $rows = [];
        $n = 0;
        foreach ($stores as $store) {
            foreach ($store->managers as $manager) {
                $rows[] = [
                    ++$n,
                    (string) $store->getTranslation('title', 'en', false),
                    (string) $store->slug,
                    (string) $manager->name,
                    (string) ($manager->position ?? ''),
                    StoreSpreadsheet::writePhones($manager->phones),
                ];
            }
        }

        $this->write($sheet, StoreSpreadsheet::managerHeaders(), $rows, '0284C7', [8, 32, 28, 30, 26, 40]);
    }

    /**
     * Header on row 1, one row per record, every cell written as text so a
     * phone keeps its leading zero and a slug is never read as a number.
     *
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $widths
     */
    private function write(Worksheet $sheet, array $headers, array $rows, string $colour, array $widths): void
    {
        foreach ($headers as $c => $label) {
            $sheet->setCellValueExplicit([$c + 1, 1], $label, DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($c + 1)->setWidth($widths[$c] ?? 20);
        }
        $last = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colour]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $value, DataType::TYPE_STRING);
            }
        }
    }
}
