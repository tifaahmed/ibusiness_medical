<?php

namespace App\Http\Controllers\Admin\Order\Export;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * XLSX export of the admin order list — same shape as the member export
 * (/admin/user/membership/export): the filters the admin is looking at are
 * carried over on the query string, printed in the sheet's filter block, and
 * the same column picker / split-into-parts options apply.
 */
class AdminOrderExportController extends BaseController
{
    // Upper bound is a safety net so a typo can't kick off a giant in-memory build.
    private const MIN_CHUNK_SIZE = 1;

    private const MAX_CHUNK_SIZE = 10000;

    private const MONEY_FORMAT = '#,##0.00';

    public function __invoke(Request $request): StreamedResponse
    {
        try {
            return $this->build($request);
        } catch (\Throwable $e) {
            // A failed export is a dead-end download for the admin — leave a
            // trail with enough of the request to reproduce it, then let the
            // exception surface as usual.
            Log::error('Order export failed', [
                'route' => $request->path(),
                'query' => $request->query(),
                'admin_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function build(Request $request): StreamedResponse
    {
        // The XLSX download returns a StreamedResponse instead of an Inertia
        // page, so HandleInertiaRequests::share() never runs and the app
        // locale stays at config('app.locale') (en). Re-resolve the locale
        // from the session — same logic as the Inertia middleware — so the
        // exported file matches the admin's active language.
        $locale = Session::get('locale', config('app.locale'));
        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

        $filters = $this->getFilters($request);

        $includeProducts = $request->boolean('include_products');

        $rawColumns = $request->input('columns', '');
        $selectedColumns = $rawColumns !== ''
            ? array_intersect(array_map('trim', explode(',', $rawColumns)), array_keys($this->getColumnDefinitions()))
            : [];

        // 0 (or anything outside the bounds) = no split → single XLSX.
        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $orders = Order::query()
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($query) use ($term) {
                    $query->where('order_code', 'like', $term)
                        ->orWhere('customer_full_name', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhere('membership_number', 'like', $term);
                });
            })
            ->when(! empty($filters['payment_status']), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when(! empty($filters['delivery_status']), fn ($q) => $q->where('delivery_status', $filters['delivery_status']))
            ->when(! empty($filters['order_status']), fn ($q) => $q->where('order_status', $filters['order_status']))
            ->when(! empty($filters['payment_type']), fn ($q) => $q->where('payment_type', $filters['payment_type']))
            ->when(! empty($filters['created_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->with('products')
            ->latest()
            ->get();

        $timestamp = now()->format('Y-m-d_His');

        // Single-file export.
        if ($chunkSize === 0 || $orders->count() <= $chunkSize) {
            $spreadsheet = $this->buildSpreadsheet($orders, $filters, $includeProducts, null, $selectedColumns);
            $filename = ($includeProducts ? 'orders_with_products_' : 'orders_export_').$timestamp.'.xlsx';

            return $this->streamXlsx($spreadsheet, $filename);
        }

        // Split mode: build one XLSX per chunk and bundle into a ZIP.
        $chunks = $orders->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir().'/orders_export_'.uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = __('admin.order_export.part_label', ['current' => $partNumber, 'total' => $totalParts]);
            $partSpreadsheet = $this->buildSpreadsheet($chunk, $filters, $includeProducts, $partLabel, $selectedColumns);
            $partFilename = sprintf(
                '%sorders_part_%02d_of_%02d.xlsx',
                $includeProducts ? 'with_products_' : '',
                $partNumber,
                $totalParts
            );
            $partPath = $tmpDir.'/'.$partFilename;
            (IOFactory::createWriter($partSpreadsheet, 'Xlsx'))->save($partPath);
            $partSpreadsheet->disconnectWorksheets();
            unset($partSpreadsheet);
            $partFiles[] = ['path' => $partPath, 'name' => $partFilename];
        }

        $zipName = sprintf(
            '%sorders_export_%s_split_%d.zip',
            $includeProducts ? 'with_products_' : '',
            $timestamp,
            $chunkSize
        );
        $zipPath = $tmpDir.'/'.$zipName;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($partFiles as $part) {
            $zip->addFile($part['path'], $part['name']);
        }
        $zip->close();

        return response()->stream(function () use ($zipPath, $tmpDir) {
            readfile($zipPath);
            // Cleanup temp files after the response has been streamed.
            foreach (glob($tmpDir.'/*') as $f) {
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

    /**
     * Same filter set the list page reads, validated the same way so a
     * tampered query string cannot widen or error the export.
     */
    protected function getFilters(Request $request): array
    {
        $paymentStatus = $request->input('payment_status');
        $deliveryStatus = $request->input('delivery_status');
        $orderStatus = $request->input('order_status');
        $paymentType = $request->input('payment_type');

        return [
            'search' => (string) $request->input('search', ''),
            'payment_status' => in_array($paymentStatus, PaymentStatusEnum::values(), true) ? $paymentStatus : '',
            'delivery_status' => in_array($deliveryStatus, DeliveryStatusEnum::values(), true) ? $deliveryStatus : '',
            'order_status' => in_array($orderStatus, OrderStatusEnum::values(), true) ? $orderStatus : '',
            'payment_type' => in_array($paymentType, PaymentTypeEnum::values(), true) ? $paymentType : '',
            'created_from' => $this->normalizeDate($request->input('created_from')),
            'created_to' => $this->normalizeDate($request->input('created_to')),
        ];
    }

    protected function normalizeDate(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : '';
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
     * The full catalogue of exportable order columns. `money` columns are
     * written as real numbers so Excel can total them; everything else is
     * forced to text (an order code or a phone number that looks numeric
     * would otherwise land in scientific notation).
     */
    protected function getColumnDefinitions(): array
    {
        return [
            'index' => ['label' => __('admin.order_export.col_index'), 'width' => 8, 'align' => Alignment::HORIZONTAL_CENTER],
            'order_code' => ['label' => __('admin.order_export.col_order_code'), 'width' => 24],
            'created_at' => ['label' => __('admin.order_export.col_created_at'), 'width' => 22, 'align' => Alignment::HORIZONTAL_CENTER],
            'customer_full_name' => ['label' => __('admin.order_export.col_customer_name'), 'width' => 34],
            'customer_phone' => ['label' => __('admin.order_export.col_customer_phone'), 'width' => 20],
            'membership_number' => ['label' => __('admin.order_export.col_membership_number'), 'width' => 24],
            'payment_status' => ['label' => __('admin.order_export.col_payment_status'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'delivery_status' => ['label' => __('admin.order_export.col_delivery_status'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'order_status' => ['label' => __('admin.order_export.col_order_status'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'payment_type' => ['label' => __('admin.order_export.col_payment_type'), 'width' => 22],
            'total_paid' => ['label' => __('admin.order_export.col_total_paid'), 'width' => 16, 'money' => true],
            'total_amount' => ['label' => __('admin.order_export.col_total_amount'), 'width' => 16, 'money' => true],
            'total_amount_before_discount' => ['label' => __('admin.order_export.col_total_before_discount'), 'width' => 20, 'money' => true],
            'discount' => ['label' => __('admin.order_export.col_discount'), 'width' => 16, 'money' => true],
            'outstanding' => ['label' => __('admin.order_export.col_outstanding'), 'width' => 16, 'money' => true],
            'delivery_cost' => ['label' => __('admin.order_export.col_delivery_cost'), 'width' => 16, 'money' => true],
            'delivery_price' => ['label' => __('admin.order_export.col_delivery_price'), 'width' => 16, 'money' => true],
            'delivery_profit' => ['label' => __('admin.order_export.col_delivery_profit'), 'width' => 16, 'money' => true],
            'products_count' => ['label' => __('admin.order_export.col_products_count'), 'width' => 12, 'align' => Alignment::HORIZONTAL_CENTER],
            'products' => ['label' => __('admin.order_export.col_products'), 'width' => 48],
            'customer_address_type' => ['label' => __('admin.order_export.col_address_type'), 'width' => 14, 'align' => Alignment::HORIZONTAL_CENTER],
            'customer_governorate' => ['label' => __('admin.order_export.col_governorate'), 'width' => 22],
            'customer_city' => ['label' => __('admin.order_export.col_city'), 'width' => 22],
            'customer_street' => ['label' => __('admin.order_export.col_street'), 'width' => 28],
            'customer_building_number' => ['label' => __('admin.order_export.col_building_number'), 'width' => 14, 'align' => Alignment::HORIZONTAL_CENTER],
            'customer_apartment_number' => ['label' => __('admin.order_export.col_apartment_number'), 'width' => 14, 'align' => Alignment::HORIZONTAL_CENTER],
            'customer_floor_number' => ['label' => __('admin.order_export.col_floor_number'), 'width' => 14, 'align' => Alignment::HORIZONTAL_CENTER],
            'customer_special_mark' => ['label' => __('admin.order_export.col_special_mark'), 'width' => 28],
            'customer_address' => ['label' => __('admin.order_export.col_address'), 'width' => 44],
            'notes' => ['label' => __('admin.order_export.col_notes'), 'width' => 36],
            'cancel_reason' => ['label' => __('admin.order_export.col_cancel_reason'), 'width' => 28],
            'source' => ['label' => __('admin.order_export.col_source'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER],
            'updated_at' => ['label' => __('admin.order_export.col_updated_at'), 'width' => 22, 'align' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    /**
     * Money columns come back as floats, everything else as a string.
     */
    protected function getColumnValue(string $key, Order $order, int $rowIndex): string|float
    {
        $none = '';

        return match ($key) {
            'index' => (string) $rowIndex,
            'order_code' => (string) $order->order_code,
            'created_at' => $order->created_at?->translatedFormat('d M Y H:i') ?? $none,
            'updated_at' => $order->updated_at?->translatedFormat('d M Y H:i') ?? $none,
            'customer_full_name' => (string) $order->customer_full_name,
            'customer_phone' => (string) $order->customer_phone,
            'membership_number' => (string) ($order->membership_number ?? $none),
            'payment_status' => $order->payment_status?->label() ?? $none,
            'delivery_status' => $order->delivery_status?->label() ?? $none,
            'order_status' => $order->order_status?->label() ?? $none,
            'payment_type' => $order->payment_type?->label() ?? $none,
            'total_paid' => (float) $order->total_paid,
            'total_amount' => (float) $order->total_amount,
            'total_amount_before_discount' => (float) ($order->total_amount_before_discount ?? $order->total_amount),
            'discount' => round(((float) ($order->total_amount_before_discount ?? $order->total_amount)) - (float) $order->total_amount, 2),
            'outstanding' => round((float) $order->total_amount - (float) $order->total_paid, 2),
            'delivery_cost' => (float) $order->delivery_cost,
            'delivery_price' => (float) $order->delivery_price,
            'delivery_profit' => (float) $order->delivery_profit,
            'products_count' => (string) $order->products->count(),
            'products' => $order->products
                ->map(fn ($line) => trim(($line->name ?? __('admin.order_export.unnamed_line')).' × '.$line->quantity))
                ->implode(' | '),
            'customer_address_type' => $order->customer_address_type?->value ?? $none,
            'customer_governorate' => (string) ($order->customer_governorate ?? $none),
            'customer_city' => (string) ($order->customer_city ?? $none),
            'customer_street' => (string) ($order->customer_street ?? $none),
            'customer_building_number' => (string) ($order->customer_building_number ?? $none),
            'customer_apartment_number' => (string) ($order->customer_apartment_number ?? $none),
            'customer_floor_number' => (string) ($order->customer_floor_number ?? $none),
            'customer_special_mark' => (string) ($order->customer_special_mark ?? $none),
            'customer_address' => (string) ($order->customer_address ?? $none),
            'notes' => (string) ($order->notes ?? $none),
            'cancel_reason' => (string) ($order->cancel_reason ?? $none),
            'source' => (string) ($order->source ?? $none),
            default => $none,
        };
    }

    /**
     * Build a styled Orders spreadsheet for the given collection. Optionally
     * adds an "Order Products" sheet — one row per sold line — the same way
     * the member export adds its family sheet.
     * $partLabel (e.g. "Part 2 of 5") appears in the title banner for split exports.
     */
    private function buildSpreadsheet(
        Collection $orders,
        array $filters,
        bool $includeProducts,
        ?string $partLabel = null,
        array $selectedColumns = []
    ): Spreadsheet {
        $isRtl = app()->getLocale() === 'ar';

        $allDefs = $this->getColumnDefinitions();
        if (! empty($selectedColumns)) {
            $allDefs = array_intersect_key($allDefs, array_flip($selectedColumns));
        }
        $colKeys = array_keys($allDefs);

        $letters = [];
        foreach ($colKeys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastLetter = count($letters) ? end($letters) : 'A';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.order_export.orders_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // ------ Title block ------
        $exportTitle = __('admin.order_export.title');
        $title = $partLabel ? "{$exportTitle} — {$partLabel}" : $exportTitle;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastLetter}1");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', __('admin.order_export.generated_at'));
        $sheet->setCellValue('B2', now()->translatedFormat('D, d M Y H:i'));
        $sheet->setCellValue('A3', __('admin.order_export.total_rows'));
        $sheet->setCellValue('B3', $orders->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Filter block (always uses A/B) ------
        $sheet->setCellValue('A5', __('admin.order_export.filters_applied'));
        $sheet->mergeCells("A5:{$lastLetter}5");
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $none = __('admin.order_export.value_none');
        $filterRows = [
            [__('admin.order_export.filter_search'), $filters['search'] !== '' ? $filters['search'] : $none],
            [__('admin.order_export.filter_payment_status'), $filters['payment_status'] !== ''
                ? (PaymentStatusEnum::getLabel($filters['payment_status']) ?? $filters['payment_status'])
                : __('admin.order_export.value_all')],
            [__('admin.order_export.filter_delivery_status'), $filters['delivery_status'] !== ''
                ? (DeliveryStatusEnum::getLabel($filters['delivery_status']) ?? $filters['delivery_status'])
                : __('admin.order_export.value_all')],
            [__('admin.order_export.filter_order_status'), $filters['order_status'] !== ''
                ? (OrderStatusEnum::getLabel($filters['order_status']) ?? $filters['order_status'])
                : __('admin.order_export.value_all')],
            [__('admin.order_export.filter_payment_type'), $filters['payment_type'] !== ''
                ? (PaymentTypeEnum::getLabel($filters['payment_type']) ?? $filters['payment_type'])
                : __('admin.order_export.value_all')],
            [__('admin.order_export.filter_created_from'), $filters['created_from'] !== '' ? $filters['created_from'] : $none],
            [__('admin.order_export.filter_created_to'), $filters['created_to'] !== '' ? $filters['created_to'] : $none],
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

        // ------ Orders table ------
        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", __('admin.order_export.orders_section'));
        $sheet->mergeCells("A{$headerRow}:{$lastLetter}{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        foreach ($colKeys as $key) {
            $sheet->setCellValue("{$letters[$key]}{$columnHeaderRow}", $allDefs[$key]['label']);
        }
        $firstCol = count($letters) ? reset($letters) : 'A';
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("{$firstCol}{$columnHeaderRow}:{$lastLetter}{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        // ------ Data rows ------
        $dataStart = $columnHeaderRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;
        foreach ($orders as $order) {
            $rowIndex++;

            foreach ($colKeys as $key) {
                $col = $letters[$key];
                $value = $this->getColumnValue($key, $order, $rowIndex);
                if (! empty($allDefs[$key]['money'])) {
                    $sheet->setCellValue("{$col}{$dataRow}", $value);
                    $sheet->getStyle("{$col}{$dataRow}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
                } else {
                    // Explicit TYPE_STRING — PhpSpreadsheet's default value
                    // binder would otherwise turn a numeric-looking order code
                    // or phone number into a real number, which Excel then
                    // renders in scientific notation.
                    $sheet->setCellValueExplicit("{$col}{$dataRow}", (string) $value, DataType::TYPE_STRING);
                }
            }

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("{$firstCol}{$dataRow}:{$lastLetter}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            foreach ($colKeys as $key) {
                if (isset($allDefs[$key]['align'])) {
                    $sheet->getStyle("{$letters[$key]}{$dataRow}")->getAlignment()->setHorizontal($allDefs[$key]['align']);
                }
            }

            $badgeMap = [
                'payment_status' => $this->paymentStatusColors($order->payment_status),
                'delivery_status' => $this->deliveryStatusColors($order->delivery_status),
                'order_status' => $this->orderStatusColors($order->order_status),
            ];
            foreach ($badgeMap as $badgeKey => $colors) {
                if (isset($letters[$badgeKey]) && $colors !== null) {
                    $sheet->getStyle("{$letters[$badgeKey]}{$dataRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $colors['fg']]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['bg']]],
                    ]);
                }
            }

            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        // ------ Column widths ------
        foreach ($colKeys as $key) {
            $sheet->getColumnDimension($letters[$key])->setWidth($allDefs[$key]['width']);
        }

        // ------ Footer ------
        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", __('admin.order_export.footer', ['count' => $orders->count()]));
        $sheet->mergeCells("A{$footerRow}:{$lastLetter}{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($includeProducts) {
            $this->buildProductsSheet($spreadsheet, $orders);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCells('A1');

        return $spreadsheet;
    }

    private function paymentStatusColors(?PaymentStatusEnum $status): ?array
    {
        return match ($status) {
            PaymentStatusEnum::ACCEPTED => ['bg' => 'D1FAE5', 'fg' => '047857'],
            PaymentStatusEnum::PENDING => ['bg' => 'FEF3C7', 'fg' => 'B45309'],
            PaymentStatusEnum::REJECTED, PaymentStatusEnum::CANCELED => ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
            default => null,
        };
    }

    private function deliveryStatusColors(?DeliveryStatusEnum $status): ?array
    {
        return match ($status) {
            DeliveryStatusEnum::COMPLETED => ['bg' => 'D1FAE5', 'fg' => '047857'],
            DeliveryStatusEnum::ON_DELIVERY => ['bg' => 'DBEAFE', 'fg' => '1D4ED8'],
            DeliveryStatusEnum::PROCESSING => ['bg' => 'E0F2FE', 'fg' => '0369A1'],
            DeliveryStatusEnum::PENDING => ['bg' => 'FEF3C7', 'fg' => 'B45309'],
            default => null,
        };
    }

    private function orderStatusColors(?OrderStatusEnum $status): ?array
    {
        return match ($status) {
            OrderStatusEnum::SUCCESS => ['bg' => 'D1FAE5', 'fg' => '047857'],
            OrderStatusEnum::PENDING => ['bg' => 'FEF3C7', 'fg' => 'B45309'],
            OrderStatusEnum::FAILED => ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
            default => null,
        };
    }

    /**
     * One row per sold line, each carrying its order's code and customer so
     * the sheet reads on its own.
     */
    private function buildProductsSheet(Spreadsheet $spreadsheet, Collection $orders): void
    {
        $isRtl = app()->getLocale() === 'ar';

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(__('admin.order_export.products_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        $sheet->setCellValue('A1', __('admin.order_export.products_section'));
        $sheet->mergeCells('A1:K1');
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
        ]);

        $headerRow = 3;
        $columns = [
            'A' => __('admin.order_export.col_index'),
            'B' => __('admin.order_export.col_order_code'),
            'C' => __('admin.order_export.col_created_at'),
            'D' => __('admin.order_export.col_customer_name'),
            'E' => __('admin.order_export.products_col_product'),
            'F' => __('admin.order_export.products_col_quantity'),
            'G' => __('admin.order_export.products_col_unit_price'),
            'H' => __('admin.order_export.products_col_old_price'),
            'I' => __('admin.order_export.products_col_line_total'),
            'J' => __('admin.order_export.products_col_cost_price'),
            'K' => __('admin.order_export.products_col_profit_price'),
        ];
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $row = $headerRow + 1;
        $index = 0;
        foreach ($orders as $order) {
            foreach ($order->products as $line) {
                $index++;
                $sheet->setCellValueExplicit("A{$row}", (string) $index, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("B{$row}", (string) $order->order_code, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("C{$row}", $order->created_at?->translatedFormat('d M Y H:i') ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", (string) $order->customer_full_name, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("E{$row}", (string) ($line->name ?? __('admin.order_export.unnamed_line')), DataType::TYPE_STRING);
                $sheet->setCellValue("F{$row}", (int) $line->quantity);
                $sheet->setCellValue("G{$row}", (float) $line->new_price);
                $sheet->setCellValue("H{$row}", (float) $line->old_price);
                $sheet->setCellValue("I{$row}", (float) $line->line_total);
                $sheet->setCellValue("J{$row}", (float) $line->cost_price);
                $sheet->setCellValue("K{$row}", (float) $line->profit_price);
                $sheet->getStyle("G{$row}:K{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);

                $stripe = ($index % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;
            }
        }

        $widths = [
            'A' => 8, 'B' => 24, 'C' => 22, 'D' => 34, 'E' => 44, 'F' => 10,
            'G' => 16, 'H' => 16, 'I' => 16, 'J' => 16, 'K' => 16,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $footerRow = $row + 1;
        $sheet->setCellValue("A{$footerRow}", __('admin.order_export.products_footer', ['count' => $index]));
        $sheet->mergeCells("A{$footerRow}:K{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setSelectedCells('A1');
    }
}
