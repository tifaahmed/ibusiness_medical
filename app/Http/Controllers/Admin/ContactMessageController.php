<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Contact\UpdateContactMessageAction;
use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\ExportsContactMessageColumns;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\Sales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
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
 * The enquiry inbox: this site's own contact form, and the three public forms
 * on the Deilar storefront (its contact page, its card popup, and facilities
 * applying to join the network).
 *
 * Every change an admin makes is recorded against the enquiry — see
 * `UpdateContactMessageAction` — so an enquiry that went quiet can be read
 * back to who had it and when.
 */
class ContactMessageController extends Controller
{
    use ExportsContactMessageColumns;

    // Upper bound is a safety net so a typo can't kick off a giant in-memory build.
    private const MIN_CHUNK_SIZE = 1;

    private const MAX_CHUNK_SIZE = 10000;

    public function __construct(private readonly UpdateContactMessageAction $updateContactMessage) {}

    /**
     * Whether this admin may actually change an enquiry.
     *
     * The read side admits the viewer role's `view contact messages`, so the
     * pages have to know the difference — a Save button that can only ever
     * answer 403 is worse than no Save button.
     */
    private function canManage(Request $request): bool
    {
        return $request->user()?->hasAnyPermission([
            UserPermissionEnum::MANAGE_CONTACT_MESSAGES,
            UserPermissionEnum::MANAGE_MEMBERSHIPS,
        ]) ?? false;
    }

    public function index(Request $request): Response
    {
        $query = ContactMessage::query()->with('sales');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->status($request->string('status')->toString());
        }

        /* Which form it came through. Sales work a join request and a card
           popup very differently, so this is a first-class filter rather than
           something to search for. */
        if ($request->filled('source') && $request->source !== 'all') {
            $query->source($request->string('source')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('commercial_register', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        /* Allow-listed rather than taken from the query string: an arbitrary
           column here is an ORDER BY an attacker chooses. */
        $sortField = in_array($request->get('sort'), ['created_at', 'status', 'source'], true)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $messages = $query->orderBy($sortField, $sortDirection)->paginate(15)->withQueryString();

        $counts = ContactMessage::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/ContactMessages/Index', [
            'messages' => ContactMessageResource::collection($messages),
            'stats' => [
                'total' => (int) $counts->sum(),
                ...collect(ContactStatusEnum::values())
                    ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
                    ->all(),
            ],
            'statuses' => array_values(ContactStatusEnum::getOptions()),
            'sources' => array_values(ContactSourceEnum::getOptions()),
            'canManage' => $this->canManage($request),
            'filters' => [
                'status' => $request->get('status', 'all'),
                'source' => $request->get('source', 'all'),
                'search' => $request->get('search', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Export the enquiries the current filters match to XLSX — same
     * status/source/search as the index list, but unpaginated. Mirrors the
     * membership export: an optional column subset (`columns`, comma list)
     * and an optional split into a ZIP of several XLSX files (`chunk_size`).
     */
    public function export(Request $request): StreamedResponse
    {
        // The XLSX/ZIP download returns a StreamedResponse instead of an
        // Inertia page, so HandleInertiaRequests::share() never runs and the
        // app locale stays at config('app.locale'). Re-resolve it from the
        // session — same logic as the Inertia middleware — so the exported
        // file matches the admin's active language.
        $locale = Session::get('locale', config('app.locale'));
        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

        $filters = [
            'search' => $request->input('search', ''),
            'status' => ($request->filled('status') && $request->status !== 'all') ? $request->string('status')->toString() : null,
            'source' => ($request->filled('source') && $request->source !== 'all') ? $request->string('source')->toString() : null,
        ];

        $rawColumns = $request->input('columns', '');
        $selectedColumns = $rawColumns !== ''
            ? array_values(array_intersect(array_map('trim', explode(',', $rawColumns)), array_keys($this->contactMessageColumnDefinitions())))
            : [];

        $rawChunk = (int) $request->input('chunk_size', 0);
        $chunkSize = ($rawChunk >= self::MIN_CHUNK_SIZE && $rawChunk <= self::MAX_CHUNK_SIZE) ? $rawChunk : 0;

        $query = ContactMessage::query()->with('sales');

        if ($filters['status'] !== null) {
            $query->status($filters['status']);
        }

        if ($filters['source'] !== null) {
            $query->source($filters['source']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('commercial_register', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderBy('created_at', 'desc')->get();

        $statusLabel = $filters['status'] !== null
            ? ContactStatusEnum::from($filters['status'])->label()
            : __('admin.contact_message_export.status_all');
        $sourceLabel = $filters['source'] !== null
            ? ContactSourceEnum::from($filters['source'])->label()
            : __('admin.contact_message_export.source_all');

        $timestamp = now()->format('Y-m-d_His');

        // Single-file export — keep the existing behavior.
        if ($chunkSize === 0 || $messages->count() <= $chunkSize) {
            $spreadsheet = $this->buildExportSpreadsheet($messages, $filters, $statusLabel, $sourceLabel, null, $selectedColumns);
            $filename = 'contact_messages_export_' . $timestamp . '.xlsx';

            return $this->streamXlsx($spreadsheet, $filename);
        }

        // Split mode: build one XLSX per chunk and bundle into a ZIP.
        $chunks = $messages->chunk($chunkSize)->values();
        $totalParts = $chunks->count();
        $tmpDir = sys_get_temp_dir() . '/contact_messages_export_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $partFiles = [];
        foreach ($chunks as $i => $chunk) {
            $partNumber = $i + 1;
            $partLabel = __('admin.contact_message_export.part_label', ['current' => $partNumber, 'total' => $totalParts]);
            $partSpreadsheet = $this->buildExportSpreadsheet($chunk, $filters, $statusLabel, $sourceLabel, $partLabel, $selectedColumns);
            $partFilename = sprintf('contact_messages_part_%02d_of_%02d.xlsx', $partNumber, $totalParts);
            $partPath = $tmpDir . '/' . $partFilename;
            (IOFactory::createWriter($partSpreadsheet, 'Xlsx'))->save($partPath);
            $partSpreadsheet->disconnectWorksheets();
            unset($partSpreadsheet);
            $partFiles[] = ['path' => $partPath, 'name' => $partFilename];
        }

        $zipName = sprintf('contact_messages_export_%s_split_%d.zip', $timestamp, $chunkSize);
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

    /**
     * @param  Collection<int, ContactMessage>  $messages
     */
    private function buildExportSpreadsheet(Collection $messages, array $filters, string $statusLabel, string $sourceLabel, ?string $partLabel, array $selectedColumns): Spreadsheet
    {
        $isRtl = app()->getLocale() === 'ar';

        $allDefs = $this->contactMessageColumnDefinitions();
        if (! empty($selectedColumns)) {
            $allDefs = array_intersect_key($allDefs, array_flip($selectedColumns));
        }
        $keys = array_keys($allDefs);

        $letters = [];
        foreach ($keys as $i => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i + 1);
        }
        $firstCol = reset($letters) ?: 'A';
        $lastCol = end($letters) ?: 'A';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.contact_message_export.sheet_title'));
        if ($isRtl) {
            $sheet->setRightToLeft(true);
        }

        // ------ Title block ------
        $exportTitle = __('admin.contact_message_export.title');
        $title = $partLabel ? "{$exportTitle} — {$partLabel}" : $exportTitle;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B8860B']],
        ]);

        $sheet->setCellValue('A2', __('admin.contact_message_export.generated_at'));
        $sheet->setCellValue('B2', now()->translatedFormat('D, d M Y H:i'));
        $sheet->setCellValue('A3', __('admin.contact_message_export.total_rows'));
        $sheet->setCellValue('B3', $messages->count());
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A2:B3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E7']],
        ]);

        // ------ Filter block ------
        $sheet->setCellValue('A5', __('admin.contact_message_export.filters_applied'));
        $sheet->mergeCells("A5:{$lastCol}5");
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $none = __('admin.contact_message_export.value_none');
        $filterRows = [
            [__('admin.contact_message_export.filter_search'), $filters['search'] !== '' ? $filters['search'] : $none],
            [__('admin.contact_message_export.filter_status'), $statusLabel],
            [__('admin.contact_message_export.filter_source'), $sourceLabel],
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

        // ------ Messages table ------
        $headerRow = $row + 2;
        $sheet->setCellValue("A{$headerRow}", __('admin.contact_message_export.messages_section'));
        $sheet->mergeCells("A{$headerRow}:{$lastCol}{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle("A{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $columnHeaderRow = $headerRow + 1;
        foreach ($keys as $key) {
            $sheet->setCellValue("{$letters[$key]}{$columnHeaderRow}", $allDefs[$key]['label']);
        }
        $sheet->getRowDimension($columnHeaderRow)->setRowHeight(26);
        $sheet->getStyle("{$firstCol}{$columnHeaderRow}:{$lastCol}{$columnHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F2937']]],
        ]);

        // ------ Data rows ------
        $dataStart = $columnHeaderRow + 1;
        $dataRow = $dataStart;
        $rowIndex = 0;
        foreach ($messages as $message) {
            $rowIndex++;

            $values = [
                'id'                  => (string) $message->id,
                'name'                => (string) ($message->name ?? ''),
                'email'               => (string) ($message->email ?? ''),
                'phone'               => (string) ($message->phone ?? ''),
                'commercial_register' => (string) ($message->commercial_register ?? ''),
                'source'              => (string) ($message->source?->label() ?? ''),
                'status'              => (string) ($message->status?->label() ?? ''),
                'sales_name'          => (string) ($message->sales?->name ?? ''),
                'subject'             => (string) ($message->subject ?? ''),
                'message'             => (string) ($message->message ?? ''),
                'admin_notes'         => (string) ($message->admin_notes ?? ''),
                'created_at'          => $message->created_at?->format('Y-m-d H:i:s') ?? '',
                'read_at'             => $message->read_at?->format('Y-m-d H:i:s') ?? '',
                'replied_at'          => $message->replied_at?->format('Y-m-d H:i:s') ?? '',
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
                if (! empty($allDefs[$key]['align'])) {
                    $sheet->getStyle("{$letters[$key]}{$dataRow}")->getAlignment()->setHorizontal($allDefs[$key]['align']);
                }
            }

            if (isset($letters['status'])) {
                $statusColors = match ($message->status?->value) {
                    'new' => ['bg' => 'DBEAFE', 'fg' => '1D4ED8'],
                    'in_progress' => ['bg' => 'FEF3C7', 'fg' => 'B45309'],
                    'resolved' => ['bg' => 'D1FAE5', 'fg' => '047857'],
                    'closed' => ['bg' => 'F3F4F6', 'fg' => '6B7280'],
                    default => null,
                };
                if ($statusColors !== null) {
                    $sheet->getStyle("{$letters['status']}{$dataRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $statusColors['fg']]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColors['bg']]],
                    ]);
                }
            }

            $sheet->getRowDimension($dataRow)->setRowHeight(22);
            $dataRow++;
        }

        foreach ($keys as $key) {
            $sheet->getColumnDimension($letters[$key])->setWidth($allDefs[$key]['width']);
        }

        // ------ Footer ------
        $footerRow = ($dataRow > $dataStart ? $dataRow : $dataStart) + 1;
        $sheet->setCellValue("A{$footerRow}", __('admin.contact_message_export.footer', ['count' => $messages->count()]));
        $sheet->mergeCells("A{$footerRow}:{$lastCol}{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCells('A1');

        return $spreadsheet;
    }

    public function show(Request $request, ContactMessage $contactMessage): Response
    {
        /* Stamps "first opened" only. It no longer moves the status: an
           enquiry somebody glanced at is still new work until it is picked up. */
        $contactMessage->markAsRead();

        $contactMessage->load(['sales', 'logs.admin']);

        return Inertia::render('Admin/ContactMessages/Show', [
            /* resolve(), not the resource itself: a single JsonResource serialises
               as { data: {...} }, but Show.vue reads the enquiry's fields straight
               off `message` — without this the page renders with every field blank. */
            'message' => (new ContactMessageResource($contactMessage))->resolve($request),
            'statuses' => array_values(ContactStatusEnum::getOptions()),
            'salesOptions' => Sales::query()
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Sales $sales) => ['value' => $sales->id, 'label' => $sales->name]),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(ContactStatusEnum::values())],
            'sales_id' => ['sometimes', 'nullable', 'exists:sales,id'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $this->updateContactMessage->handle($contactMessage, $validated, $request->user());

        return redirect()->back()->with('success', 'Contact message updated successfully.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }

    /**
     * Move or delete several at once.
     *
     * Each one goes through the same action a single edit does, so a bulk
     * change leaves the same trail behind it as fifty individual ones.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:contact_messages,id'],
            'action' => ['required', 'string', Rule::in([...ContactStatusEnum::values(), 'delete'])],
        ]);

        $messages = ContactMessage::query()->whereIn('id', $validated['ids'])->get();

        if ($validated['action'] === 'delete') {
            ContactMessage::query()->whereIn('id', $validated['ids'])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Messages deleted successfully.',
            ]);
        }

        foreach ($messages as $message) {
            $this->updateContactMessage->handle(
                $message,
                ['status' => $validated['action']],
                $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages updated successfully.',
        ]);
    }
}
