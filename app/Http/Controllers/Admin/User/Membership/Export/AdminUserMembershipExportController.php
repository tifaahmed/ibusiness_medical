<?php

namespace App\Http\Controllers\Admin\User\Membership\Export;

use App\Http\Controllers\Concerns\ExportsMemberColumns;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\MembershipCard;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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

class AdminUserMembershipExportController extends BaseController
{
    use ScopesByMembershipCreator;
    use ExportsMemberColumns;

    // Upper bound is a safety net so a typo can't kick off a giant in-memory build.
    private const MIN_CHUNK_SIZE = 1;
    private const MAX_CHUNK_SIZE = 10000;

    public function __invoke(Request $request): StreamedResponse
    {
        // The XLSX download returns a StreamedResponse instead of an Inertia
        // page, so HandleInertiaRequests::share() never runs and the app
        // locale stays at config('app.locale') (en). Re-resolve the locale
        // from the session — same logic as the Inertia middleware — so the
        // exported file matches the admin's active language.
        $locale = Session::get('locale', config('app.locale'));
        if (!in_array($locale, ['en', 'ar'], true)) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

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
            'company_id' => $request->filled('company_id') ? (int) $request->input('company_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
            'registration_date_from' => $request->filled('registration_date_from') ? $request->input('registration_date_from') : null,
            'registration_date_to' => $request->filled('registration_date_to') ? $request->input('registration_date_to') : null,
            'expiration_date_from' => $request->filled('expiration_date_from') ? $request->input('expiration_date_from') : null,
            'expiration_date_to' => $request->filled('expiration_date_to') ? $request->input('expiration_date_to') : null,
            'is_from_card_patch' => $request->has('is_from_card_patch') ? (bool) $request->input('is_from_card_patch') : null,
            'last_activation_changer' => $request->input('last_activation_changer', ''),
            'last_activation_from' => $request->filled('last_activation_from') ? $request->input('last_activation_from') : null,
            'last_activation_to' => $request->filled('last_activation_to') ? $request->input('last_activation_to') : null,
        ];
        $isActiveFilter = $filters['is_active'];
        $isPaidFilter = $filters['is_paid'];
        $partnerId = $filters['partner_id'];
        $creatorFilterId = $filters['creator_id'];
        $saleIdFilter = $filters['sale_id'];
        $companyIdFilter = $filters['company_id'];
        $membershipNumberFilter = $filters['membership_number'];
        $isFromCardPatch = $filters['is_from_card_patch'];
        $includeFamily = $request->boolean('include_family');

        $rawColumns = $request->input('columns', '');
        $selectedColumns = $rawColumns !== ''
            ? array_intersect(array_map('trim', explode(',', $rawColumns)), array_keys($this->getMemberColumnDefinitions()))
            : [];

        // 0 (or anything outside the bounds) = no split → single XLSX.
        // Admin can pick any chunk size; we clamp to [MIN, MAX] to avoid abuse.
        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        // Row-level scoping. own/partner admins only export rows in their
        // union scope; full-access admins (manage memberships) export
        // everything as before.
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        // Mirror the admin list view: never export in-progress (placeholder)
        // memberships, and always compose with row-level scoping when set.
        $listingFilter = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        $cardPatchMembershipIds = MembershipCard::query()
            ->whereNotNull('membership_ids')
            ->lazyById(200)
            ->flatMap(fn(MembershipCard $card) => $card->membership_ids ?? [])
            ->unique()
            ->values()
            ->toArray();

        $users = User::query()
            ->whereNull('deleted_at')
            ->whereHas('memberships', $listingFilter)
            ->when(!empty($filters['search']), fn($q) => $q->where('name', 'like', '%' . $filters['search'] . '%'))
            ->when(!empty($filters['email']), fn($q) => $q->where('email', 'like', '%' . $filters['email'] . '%'))
            ->when(!empty($filters['phone']), fn($q) => $q->where('phone', 'like', '%' . $filters['phone'] . '%'))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->when(!empty($filters['registration_date_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('registration_date', '>=', $filters['registration_date_from']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['registration_date_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('registration_date', '<=', $filters['registration_date_to']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['expiration_date_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('expiration_date', '>=', $filters['expiration_date_from']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['expiration_date_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('expiration_date', '<=', $filters['expiration_date_to']);
                $listingFilter($mq);
            }))
            ->when($filters['is_active'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', true);
                $listingFilter($mq);
            }))
            ->when($filters['is_active'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', false);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', true);
                $listingFilter($mq);
            }))
            ->when($isPaidFilter === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_paid', true);
                $listingFilter($mq);
            }))
            ->when($isPaidFilter === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_paid', false);
                $listingFilter($mq);
            }))
            ->when($partnerId !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($partnerId, $listingFilter) {
                $mq->where('partner_id', $partnerId);
                $listingFilter($mq);
            }))
            ->when($creatorFilterId !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($creatorFilterId, $listingFilter) {
                $mq->where('created_by', $creatorFilterId);
                $listingFilter($mq);
            }))
            ->when($saleIdFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($saleIdFilter, $listingFilter) {
                $mq->where('sales_id', $saleIdFilter);
                $listingFilter($mq);
            }))
            ->when($companyIdFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($companyIdFilter, $listingFilter) {
                $mq->where('company_id', $companyIdFilter);
                $listingFilter($mq);
            }))
            ->when(!empty($membershipNumberFilter), fn($q) => $q->whereHas('memberships', function ($mq) use ($membershipNumberFilter, $listingFilter) {
                $mq->where('membership_number', $membershipNumberFilter);
                $listingFilter($mq);
            }))
            ->when($isFromCardPatch === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            }))
            ->when($isFromCardPatch === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereNotIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_changer']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', fn($ahq) => $ahq->whereHas('changer', fn($cq) => $cq->where('name', 'like', '%'.$filters['last_activation_changer'].'%')));
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', fn($ahq) => $ahq->whereDate('created_at', '>=', $filters['last_activation_from']));
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', fn($ahq) => $ahq->whereDate('created_at', '<=', $filters['last_activation_to']));
                $listingFilter($mq);
            }))
            ->with(['memberships' => function ($q) use ($isActiveFilter, $isPaidFilter, $partnerId, $creatorFilterId, $saleIdFilter, $companyIdFilter, $membershipNumberFilter, $includeFamily, $listingFilter) {
                if ($isActiveFilter === true) {
                    $q->active();
                } elseif ($isActiveFilter === false) {
                    $q->inactive();
                }
                $listingFilter($q);
                if ($isPaidFilter !== null) {
                    $q->where('is_paid', $isPaidFilter);
                }
                if ($partnerId !== null) {
                    $q->where('partner_id', $partnerId);
                }
                if ($creatorFilterId !== null) {
                    $q->where('created_by', $creatorFilterId);
                }
                if ($saleIdFilter !== null) {
                    $q->where('sales_id', $saleIdFilter);
                }
                if ($companyIdFilter !== null) {
                    $q->where('company_id', $companyIdFilter);
                }
                if (!empty($membershipNumberFilter)) {
                    $q->where('membership_number', $membershipNumberFilter);
                }
                $q->orderBy('is_active', 'desc');
                $q->ordered();
                $q->with(['company', 'partner', 'creator:id,name,email', 'memberPayments', 'latestActiveHistory', 'sales', 'governorate', 'city']);
                $q->withCount('familyMembers');
                if ($includeFamily) {
                    $q->with(['familyMembers' => fn($fq) => $fq->orderBy('created_at')]);
                }
            }])
            ->latest()
            ->get();

        $partnerName = $partnerId !== null
            ? (Partner::find($partnerId)?->title ?? "#{$partnerId}")
            : __('admin.member_export.partner_all');
        $creatorName = $creatorFilterId !== null
            ? (User::find($creatorFilterId)?->name ?? "#{$creatorFilterId}")
            : __('admin.member_export.creator_all');
        $companyName = $companyIdFilter !== null
            ? (function () use ($companyIdFilter) {
                $c = Company::find($companyIdFilter);
                return $c
                    ? ($c->getTranslation('name', app()->getLocale()) ?: ($c->getTranslation('name', 'ar') ?: $c->getTranslation('name', 'en')))
                    : "#{$companyIdFilter}";
            })()
            : __('admin.member_export.company_all');

        $statusLabel = $filters['is_active'] === null
            ? __('admin.member_export.status_all')
            : ($filters['is_active'] ? __('admin.member_export.status_active_only') : __('admin.member_export.status_inactive_only'));
        $paidLabel = $filters['is_paid'] === null
            ? __('admin.member_export.paid_all')
            : ($filters['is_paid'] ? __('admin.member_export.paid_paid_only') : __('admin.member_export.paid_unpaid_only'));
        $sourceLabel = $filters['is_from_card_patch'] === null
            ? __('admin.member_export.source_all')
            : ($filters['is_from_card_patch'] ? __('admin.member_export.source_batch') : __('admin.member_export.source_manual'));

        $saleLabel = $saleIdFilter !== null
            ? (\App\Models\Sales::find($saleIdFilter)?->getTranslation('name', app()->getLocale())
                ?: \App\Models\Sales::find($saleIdFilter)?->getTranslation('name', 'ar')
                ?: \App\Models\Sales::find($saleIdFilter)?->getTranslation('name', 'en')
                ?: "#{$saleIdFilter}")
            : __('admin.member_export.sale_all');

        $timestamp = now()->format('Y-m-d_His');

        // Single-file export — keep the existing behavior.
        if ($chunkSize === 0 || $users->count() <= $chunkSize) {
            $spreadsheet = $this->buildSpreadsheet($users, $partnerName, $creatorName, $statusLabel, $paidLabel, $sourceLabel, $saleLabel, $filters, $includeFamily, null, $selectedColumns, $companyName);
            $filename = ($includeFamily ? 'members_with_family_' : 'members_export_') . $timestamp . '.xlsx';
            return $this->streamXlsx($spreadsheet, $filename);
        }

        // Split mode: build one XLSX per chunk and bundle into a ZIP.
        $chunks = $users->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir() . '/members_export_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = __('admin.member_export.part_label', ['current' => $partNumber, 'total' => $totalParts]);
                    $partSpreadsheet = $this->buildSpreadsheet(
                        $chunk,
                        $partnerName,
                        $creatorName,
                        $statusLabel,
                        $paidLabel,
                        $sourceLabel,
                        $saleLabel,
                        $filters,
                        $includeFamily,
                        $partLabel,
                        $selectedColumns,
                        $companyName
                    );
            $partFilename = sprintf(
                '%smembers_part_%02d_of_%02d.xlsx',
                $includeFamily ? 'with_family_' : '',
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
            '%smembers_export_%s_split_%d.zip',
            $includeFamily ? 'with_family_' : '',
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
            // Cleanup temp files after the response has been streamed.
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

    /**
     * Build a styled Members spreadsheet for the given users collection.
     * Optionally adds a "Family Members" sheet when $includeFamily is true.
     * $partLabel (e.g. "Part 2 of 5") appears in the title banner for split exports.
     */
    private function buildSpreadsheet(
        Collection $users,
        string $partnerName,
        string $creatorName,
        string $statusLabel,
        string $paidLabel,
        string $sourceLabel,
        string $saleLabel,
        array $filters,
        bool $includeFamily,
        ?string $partLabel = null,
        array $selectedColumns = [],
        string $companyName = ''
    ): Spreadsheet {
        $isRtl = app()->getLocale() === 'ar';

        $allDefs = $this->getMemberColumnDefinitions();
        if (!empty($selectedColumns)) {
            $allDefs = array_intersect_key($allDefs, array_flip($selectedColumns));
        }
        $colKeys = array_keys($allDefs);
        $colCount = count($allDefs);

        $letters = [];
        foreach ($colKeys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $lastLetter = count($letters) ? end($letters) : 'A';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.member_export.members_sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // ------ Title block ------
        $exportTitle = __('admin.member_export.title');
        $title = $partLabel ? "{$exportTitle} — {$partLabel}" : $exportTitle;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastLetter}1");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', __('admin.member_export.generated_at'));
        $sheet->setCellValue('B2', now()->translatedFormat('D, d M Y H:i'));
        $sheet->setCellValue('A3', __('admin.member_export.total_rows'));
        $sheet->setCellValue('B3', $users->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Filter block (always uses A/B) ------
        $sheet->setCellValue('A5', __('admin.member_export.filters_applied'));
        $sheet->mergeCells("A5:{$lastLetter}5");
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $none = __('admin.member_export.value_none');
        $filterRows = [
            [__('admin.member_export.filter_search'), $filters['search'] !== '' ? $filters['search'] : $none],
            [__('admin.member_export.filter_email'), $filters['email'] !== '' ? $filters['email'] : $none],
            [__('admin.member_export.filter_phone'), $filters['phone'] !== '' ? $filters['phone'] : $none],
            [__('admin.member_export.filter_membership_number'), $filters['membership_number'] !== '' ? $filters['membership_number'] : $none],
            [__('admin.member_export.filter_status'), $statusLabel],
            [__('admin.member_export.filter_payment'), $paidLabel],
            [__('admin.member_export.filter_source'), $sourceLabel],
            [__('admin.member_export.filter_partner'), $partnerName],
            [__('admin.member_export.filter_creator'), $creatorName],
            [__('admin.member_export.filter_sales'), $saleLabel],
            [__('admin.member_export.filter_company'), $companyName],
            [__('admin.member_export.filter_created_from'), $filters['created_from'] ?: $none],
            [__('admin.member_export.filter_created_to'), $filters['created_to'] ?: $none],
            [__('admin.member_export.filter_registration_date_from'), $filters['registration_date_from'] ?: $none],
            [__('admin.member_export.filter_registration_date_to'), $filters['registration_date_to'] ?: $none],
            [__('admin.member_export.filter_expiration_date_from'), $filters['expiration_date_from'] ?: $none],
            [__('admin.member_export.filter_expiration_date_to'), $filters['expiration_date_to'] ?: $none],
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

        // ------ Members table ------
        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", __('admin.member_export.members_section'));
        $sheet->mergeCells("A{$headerRow}:{$lastLetter}{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        foreach ($colKeys as $key) {
            $col = $letters[$key];
            $sheet->setCellValue("{$col}{$columnHeaderRow}", $allDefs[$key]['label']);
        }
        $firstCol = reset($letters);
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
        foreach ($users as $user) {
            $membership = $user->memberships->first();
            $rowIndex++;

            foreach ($colKeys as $key) {
                $col = $letters[$key];
                // Explicit TYPE_STRING — every column value is already cast to
                // (string) above, but PhpSpreadsheet's default value binder
                // still auto-detects numeric-looking strings (e.g. a 14-digit
                // National ID) and stores them as real numbers, which Excel
                // then renders in scientific notation. Forcing the type keeps
                // them as literal text.
                $sheet->setCellValueExplicit("{$col}{$dataRow}", $this->getColumnValue($key, $user, $membership, $rowIndex), DataType::TYPE_STRING);
            }

            $stripe = ($rowIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle("{$firstCol}{$dataRow}:{$lastLetter}{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            foreach ($colKeys as $key) {
                $def = $allDefs[$key];
                $col = $letters[$key];
                if (isset($def['align'])) {
                    $sheet->getStyle("{$col}{$dataRow}")->getAlignment()->setHorizontal($def['align']);
                }
            }

            if ($membership !== null) {
                $badgeMap = [
                    'status' => $membership->is_active
                        ? ['bg' => 'D1FAE5', 'fg' => '047857']
                        : ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
                    'payment' => $membership->is_paid
                        ? ['bg' => 'D1FAE5', 'fg' => '065F46']
                        : ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
                    'visibility' => $membership->is_visible
                        ? ['bg' => 'DBEAFE', 'fg' => '1D4ED8']
                        : ['bg' => 'FFEDD5', 'fg' => 'C2410C'],
                    'card_patch' => isset($this->getCardBatchMap()[$membership->id])
                        ? ['bg' => 'E0F2FE', 'fg' => '0369A1']
                        : null,
                ];
                foreach (['status', 'payment', 'visibility', 'card_patch'] as $badgeKey) {
                    if (isset($letters[$badgeKey]) && isset($badgeMap[$badgeKey])) {
                        $colors = $badgeMap[$badgeKey];
                        if ($colors !== null) {
                            $sheet->getStyle("{$letters[$badgeKey]}{$dataRow}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => $colors['fg']]],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['bg']]],
                            ]);
                        }
                    }
                }
            }
            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        // ------ Column widths & visibility ------
        foreach ($colKeys as $key) {
            $def = $allDefs[$key];
            $col = $letters[$key];
            $sheet->getColumnDimension($col)->setWidth($def['width']);
            if (!empty($def['hidden'])) {
                $sheet->getColumnDimension($col)->setVisible(false);
            }
        }

        // ------ Footer ------
        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", __('admin.member_export.footer', ['count' => $users->count()]));
        $sheet->mergeCells("A{$footerRow}:{$lastLetter}{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ------ Optional Family Members sheet ------
        if ($includeFamily) {
            $this->buildFamilySheet($spreadsheet, $users);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCells('A1');

        return $spreadsheet;
    }

    private function buildFamilySheet(Spreadsheet $spreadsheet, Collection $users): void
    {
        $isRtl = app()->getLocale() === 'ar';

        $famSheet = $spreadsheet->createSheet();
        $famSheet->setTitle(__('admin.member_export.family_sheet_title'));
        if ($isRtl) {
            $famSheet->setRightToLeft(true);
        }

        $famSheet->setCellValue('A1', __('admin.member_export.family_section'));
        $famSheet->mergeCells('A1:L1');
        $famSheet->getRowDimension(1)->setRowHeight(34);
        $famSheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
        ]);

        $famHeaderRow = 3;
        $famColumns = [
            'A' => __('admin.member_export.col_index'),
            'B' => __('admin.member_export.family_col_member_name'),
            'C' => __('admin.member_export.col_membership_number'),
            'D' => __('admin.member_export.family_col_family_member_name'),
            'E' => __('admin.member_export.family_col_relationship'),
            'F' => __('admin.member_export.family_col_date_of_birth'),
            'G' => __('admin.member_export.col_phone'),
            'H' => __('admin.member_export.col_email'),
            'I' => __('admin.member_export.col_status'),
            'J' => __('admin.member_export.col_created_at'),
            'K' => __('admin.member_export.col_updated_at'),
            'L' => __('admin.member_export.family_col_photo_url'),
        ];
        foreach ($famColumns as $col => $label) {
            $famSheet->setCellValue("{$col}{$famHeaderRow}", $label);
        }
        $famSheet->getRowDimension($famHeaderRow)->setRowHeight(26);
        $famSheet->getStyle("A{$famHeaderRow}:L{$famHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        $famRow = $famHeaderRow + 1;
        $famIndex = 0;
        foreach ($users as $user) {
            $membership = $user->memberships->first();
            if ($membership === null || !$membership->relationLoaded('familyMembers')) {
                continue;
            }
            foreach ($membership->familyMembers as $fm) {
                $famIndex++;
                $famSheet->setCellValue("A{$famRow}", $famIndex);
                $famSheet->setCellValue("B{$famRow}", (string) $user->name);
                $famSheet->setCellValue("C{$famRow}", (string) $membership->membership_number);
                $famSheet->setCellValue("D{$famRow}", (string) $fm->name);
                $famSheet->setCellValue("E{$famRow}", $fm->relationship?->value ?? (string) $fm->getRawOriginal('relationship'));
                $famSheet->setCellValue("F{$famRow}", $fm->date_of_birth?->translatedFormat('d M Y') ?? '');
                $famSheet->setCellValue("G{$famRow}", (string) ($fm->phone ?? ''));
                $famSheet->setCellValue("H{$famRow}", (string) ($fm->email ?? ''));
                $famSheet->setCellValue("I{$famRow}", $fm->is_active ? __('admin.member_export.badge_active') : __('admin.member_export.badge_inactive'));
                $famSheet->setCellValue("J{$famRow}", $fm->created_at?->translatedFormat('d M Y H:i') ?? '');
                $famSheet->setCellValue("K{$famRow}", $fm->updated_at?->translatedFormat('d M Y H:i') ?? '');
                $famSheet->setCellValue("L{$famRow}", get_image_url($fm, 'photo'));

                $stripe = ($famIndex % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                $famSheet->getStyle("A{$famRow}:L{$famRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $stripe]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $famSheet->getStyle("A{$famRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $famSheet->getStyle("F{$famRow}:F{$famRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $famSheet->getStyle("I{$famRow}:K{$famRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $statusColor = $fm->is_active ? ['bg' => 'D1FAE5', 'fg' => '047857'] : ['bg' => 'FEE2E2', 'fg' => 'B91C1C'];
                $famSheet->getStyle("I{$famRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $statusColor['fg']]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColor['bg']]],
                ]);
                $famSheet->getRowDimension($famRow)->setRowHeight(22);
                $famRow++;
            }
        }

        $famWidths = [
            'A' => 12, 'B' => 36, 'C' => 24, 'D' => 36, 'E' => 18, 'F' => 18,
            'G' => 20, 'H' => 32, 'I' => 14, 'J' => 22, 'K' => 22,
            'L' => 50, // Photo URL — hidden but width kept reasonable in case admin unhides.
        ];
        foreach ($famWidths as $col => $width) {
            $famSheet->getColumnDimension($col)->setWidth($width);
        }
        $famSheet->getColumnDimension('L')->setVisible(false);

        $famFooterRow = $famRow + 1;
        $famSheet->setCellValue("A{$famFooterRow}", __('admin.member_export.family_footer', ['count' => $famIndex]));
        $famSheet->mergeCells("A{$famFooterRow}:L{$famFooterRow}");
        $famSheet->getStyle("A{$famFooterRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Park the family sheet's cursor at A1 too, so it opens at the right
        // edge in RTL mode if the admin clicks the tab.
        $famSheet->setSelectedCells('A1');
    }
}
