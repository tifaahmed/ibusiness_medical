<?php

namespace App\Http\Controllers\Admin\MemberPayment\Export;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\MembershipCard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMemberPaymentExportToPayController extends BaseController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'membership_number' => $request->input('membership_number', ''),
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : null,
            'is_paid' => $request->has('is_paid') ? (bool) $request->input('is_paid') : null,
            'partner_id' => $request->filled('partner_id') ? (int) $request->input('partner_id') : null,
            'creator_id' => $request->filled('creator_id') ? (int) $request->input('creator_id') : null,
            'sale_id' => $request->filled('sale_id') ? (int) $request->input('sale_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
            'registration_date_from' => $request->filled('registration_date_from') ? $request->input('registration_date_from') : null,
            'registration_date_to' => $request->filled('registration_date_to') ? $request->input('registration_date_to') : null,
            'expiration_date_from' => $request->filled('expiration_date_from') ? $request->input('expiration_date_from') : null,
            'expiration_date_to' => $request->filled('expiration_date_to') ? $request->input('expiration_date_to') : null,
            'is_from_card_patch' => $request->has('is_from_card_patch') ? (bool) $request->input('is_from_card_patch') : null,
        ];

        $partnerId = $filters['partner_id'];

        $memberships = Membership::query()
            ->whereNotNull('completed_at')
            ->when($partnerId !== null, fn($q) => $q->where('partner_id', $partnerId))
            ->whereHas('user', fn($uq) => $uq
                ->whereNull('deleted_at')
                ->when(!empty($filters['search']), fn($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
                ->when(!empty($filters['email']), fn($q) => $q->where('email', 'like', '%'.$filters['email'].'%'))
                ->when(!empty($filters['phone']), fn($q) => $q->where('phone', 'like', '%'.$filters['phone'].'%'))
            )
            ->when(!empty($filters['membership_number']), fn($q) => $q->where('membership_number', $filters['membership_number']))
            ->when($filters['is_active'] === true, fn($q) => $q->where('is_active', true))
            ->when($filters['is_active'] === false, fn($q) => $q->where('is_active', false))
            ->when($filters['is_paid'] === true, fn($q) => $q->where('is_paid', true))
            ->when($filters['is_paid'] === false, fn($q) => $q->where('is_paid', false))
            ->when($filters['creator_id'] !== null, fn($q) => $q->where('created_by', $filters['creator_id']))
            ->when($filters['sale_id'] !== null, fn($q) => $q->where('sales_id', $filters['sale_id']))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereHas('user', fn($uq) => $uq->whereDate('created_at', '>=', $filters['created_from'])))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereHas('user', fn($uq) => $uq->whereDate('created_at', '<=', $filters['created_to'])))
            ->when(!empty($filters['registration_date_from']), fn($q) => $q->whereDate('registration_date', '>=', $filters['registration_date_from']))
            ->when(!empty($filters['registration_date_to']), fn($q) => $q->whereDate('registration_date', '<=', $filters['registration_date_to']))
            ->when(!empty($filters['expiration_date_from']), fn($q) => $q->whereDate('expiration_date', '>=', $filters['expiration_date_from']))
            ->when(!empty($filters['expiration_date_to']), fn($q) => $q->whereDate('expiration_date', '<=', $filters['expiration_date_to']))
            ->when($filters['is_from_card_patch'] === true, function ($q) {
                $cardPatchIds = MembershipCard::query()
                    ->whereNotNull('membership_ids')
                    ->lazyById(200)
                    ->flatMap(fn($c) => $c->membership_ids ?? [])
                    ->unique()
                    ->values()
                    ->toArray();
                $q->whereIn('id', $cardPatchIds);
            })
            ->when($filters['is_from_card_patch'] === false, function ($q) {
                $cardPatchIds = MembershipCard::query()
                    ->whereNotNull('membership_ids')
                    ->lazyById(200)
                    ->flatMap(fn($c) => $c->membership_ids ?? [])
                    ->unique()
                    ->values()
                    ->toArray();
                $q->whereNotIn('id', $cardPatchIds);
            })
            ->with(['user', 'partner'])
            ->ordered()
            ->get();

        $spreadsheet = $this->buildSpreadsheet($memberships, $filters);

        $timestamp = now()->format('Y-m-d_His');
        $filename = "export_to_pay_{$timestamp}.xlsx";

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

    private function buildSpreadsheet(Collection $memberships, array $filters = []): Spreadsheet
    {
        $columns = [
            'A' => ['label' => 'Name', 'width' => 40],
            'B' => ['label' => 'Email', 'width' => 36],
            'C' => ['label' => 'Phone', 'width' => 20],
            'D' => ['label' => 'Membership #', 'width' => 26],
            'E' => ['label' => 'Partner', 'width' => 28],
            'F' => ['label' => 'Amount Paid', 'width' => 18],
            'G' => ['label' => 'Type', 'width' => 18],
        ];

        $lastCol = 'G';
        $headerRow = 1;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Members');

        // Title row
        $sheet->setCellValue('A1', 'Export to Pay');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        // Info row
        $sheet->setCellValue('A2', 'Generated at');
        $sheet->setCellValue('B2', now()->format('D, d M Y H:i'));
        $sheet->setCellValue('A3', 'Member count');
        $sheet->setCellValue('B3', $memberships->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // Instruction row
        $sheet->setCellValue('A4', 'Fill in the amount_paid and type columns, then import this file back.');
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Filters Applied section
        $filterStart = 6;
        $sheet->setCellValue("A{$filterStart}", 'Filters Applied');
        $sheet->mergeCells("A{$filterStart}:{$lastCol}{$filterStart}");
        $sheet->getRowDimension($filterStart)->setRowHeight(24);
        $sheet->getStyle("A{$filterStart}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);

        $none = '—';
        $partnerName = !empty($filters['partner_id'])
            ? (\App\Models\Partner::find($filters['partner_id'])?->title ?? '#' . $filters['partner_id'])
            : $none;
        $creatorName = !empty($filters['creator_id'])
            ? (\App\Models\User::find($filters['creator_id'])?->name ?? '#' . $filters['creator_id'])
            : $none;
        $saleName = !empty($filters['sale_id'])
            ? (\App\Models\Sales::find($filters['sale_id'])?->getTranslation('name', app()->getLocale())
                ?: \App\Models\Sales::find($filters['sale_id'])?->getTranslation('name', 'ar')
                ?: \App\Models\Sales::find($filters['sale_id'])?->getTranslation('name', 'en')
                ?: '#' . $filters['sale_id'])
            : $none;
        $statusLabel = $filters['is_active'] === null ? $none : ($filters['is_active'] ? 'Active only' : 'Inactive only');
        $paidLabel = $filters['is_paid'] === null ? $none : ($filters['is_paid'] ? 'Paid only' : 'Unpaid only');
        $sourceLabel = $filters['is_from_card_patch'] === null ? $none : ($filters['is_from_card_patch'] ? 'Card patch only' : 'Manual only');

        $filterRows = [
            ['Search', !empty($filters['search']) ? $filters['search'] : $none],
            ['Email', !empty($filters['email']) ? $filters['email'] : $none],
            ['Phone', !empty($filters['phone']) ? $filters['phone'] : $none],
            ['Membership #', !empty($filters['membership_number']) ? $filters['membership_number'] : $none],
            ['Status', $statusLabel],
            ['Payment', $paidLabel],
            ['Source', $sourceLabel],
            ['Partner', $partnerName],
            ['Creator', $creatorName],
            ['Sales', $saleName],
            ['Created from', !empty($filters['created_from']) ? $filters['created_from'] : $none],
            ['Created to', !empty($filters['created_to']) ? $filters['created_to'] : $none],
            ['Registration from', !empty($filters['registration_date_from']) ? $filters['registration_date_from'] : $none],
            ['Registration to', !empty($filters['registration_date_to']) ? $filters['registration_date_to'] : $none],
            ['Expiration from', !empty($filters['expiration_date_from']) ? $filters['expiration_date_from'] : $none],
            ['Expiration to', !empty($filters['expiration_date_to']) ? $filters['expiration_date_to'] : $none],
        ];
        $row = $filterStart + 1;
        foreach ($filterRows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $filterEnd = $row - 1;
        $sheet->getStyle("A{$filterStart}:A{$filterEnd}")->getFont()->setBold(true);
        $sheet->getStyle("A{$filterStart}:B{$filterEnd}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        // Column headers
        $headerRow = $row + 2;
        foreach ($columns as $col => $def) {
            $sheet->setCellValue("{$col}{$headerRow}", $def['label']);
        }
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        // Data rows
        $dataStart = $headerRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;

        foreach ($memberships as $membership) {
            $user = $membership->user;
            if (!$user) continue;

            $rowIndex++;

            $sheet->setCellValue("A{$dataRow}", (string) ($user->name ?? ''));
            $sheet->setCellValue("B{$dataRow}", (string) ($user->email ?? ''));
            $sheet->setCellValue("C{$dataRow}", (string) ($user->phone ?? ''));
            $sheet->setCellValue("D{$dataRow}", (string) $membership->membership_number);
            $sheet->setCellValue("E{$dataRow}", (string) ($membership->partner?->title ?? ''));
            $sheet->setCellValue("F{$dataRow}", '');
            $sheet->setCellValue("G{$dataRow}", 'Commission');

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        // Dropdown validation for the type column (G)
        $lastDataRow = $dataRow - 1;
        if ($lastDataRow >= $dataStart) {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"Commission,Profit,Free"');
            $validation->setErrorTitle('Invalid type');
            $validation->setError('Please select Commission, Profit, or Free.');
            $sheet->setDataValidation("G{$dataStart}:G{$lastDataRow}", $validation);
        }

        // Column widths
        foreach ($columns as $col => $def) {
            $sheet->getColumnDimension($col)->setWidth($def['width']);
        }

        // Footer
        $footerRow = $dataRow + 1;
        $sheet->setCellValue("A{$footerRow}", "{$rowIndex} member(s) — fill in amount_paid and type to proceed.");
        $sheet->mergeCells("A{$footerRow}:{$lastCol}{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCells('A1');

        return $spreadsheet;
    }
}
