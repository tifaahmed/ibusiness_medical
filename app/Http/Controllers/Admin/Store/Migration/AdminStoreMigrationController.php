<?php

namespace App\Http\Controllers\Admin\Store\Migration;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Sales;
use App\Models\StoreCategory;
use App\Services\StoreMigration\StoreMigrationExporter;
use App\Services\StoreMigration\StoreMigrationImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store migration packages: data + images, in parts, restored in steps.
 *
 * Moving or merging a whole catalogue is an all-stores job, so every action
 * here needs the FULL stores permission — "manage own stores" cannot reach it.
 */
class AdminStoreMigrationController extends BaseController
{
    use CreatorScoped;

    /** Stores handled per import step. */
    private const STEP_SIZE = 5;

    public function __construct(private StoreMigrationExporter $exporter, private StoreMigrationImporter $importer) {}

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    private function requireFull(): void
    {
        abort_if($this->scopesToCreator(), 403, 'Store migration needs the full "manage stores" permission.');
    }

    public function page(): Response
    {
        $this->requireFull();
        $this->importer->purgeStale();

        return Inertia::render('Admin/Store/Migration/StoreMigrationView', [
            'categories' => StoreCategory::query()->orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->getTranslations('name')])->values(),
            'salesOptions' => Sales::pickerOptions(),
            'packages' => $this->listPackages(),
            'totalStores' => $this->exporter->query()->count(),
        ]);
    }

    /* ------------------------------ export ------------------------------ */

    public function plan(Request $request): JsonResponse
    {
        $this->requireFull();
        $filters = $this->filters($request);
        $total = $this->exporter->query($filters)->count();
        $perPart = max(1, min(500, (int) $request->input('per_part', 25)));

        return response()->json([
            'total' => $total,
            'per_part' => $perPart,
            'parts' => $total > 0 ? (int) ceil($total / $perPart) : 0,
            'filters_described' => $this->exporter->describeFilters($filters),
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->requireFull();
        // Walking the media library and zipping images can outlast a shared host's default.
        set_time_limit(300);

        $filters = $this->filters($request);
        $perPart = max(1, min(500, (int) $request->input('per_part', 25)));
        $part = max(1, (int) $request->input('part', 1));
        $withMedia = ! $request->has('include_media') || $request->boolean('include_media');
        $keep = ! $request->has('keep') || $request->boolean('keep');

        $query = $this->exporter->query($filters);
        $total = (clone $query)->count();
        $parts = max(1, (int) ceil($total / $perPart));
        if ($total === 0) {
            return response()->json(['message' => 'No stores match these filters.'], 422);
        }
        if ($part > $parts) {
            return response()->json(['message' => "There are only {$parts} part(s)."], 422);
        }

        $name = sprintf('stores_%s_part_%02d_of_%02d.zip', now()->format('Y-m-d_His'), $part, $parts);
        $path = ($keep ? StoreMigrationExporter::packageDirectory() : sys_get_temp_dir()).'/'.$name;

        try {
            $stores = $query->skip(($part - 1) * $perPart)->take($perPart)->get();
            $built = $this->exporter->build($stores, $path, $withMedia, [
                'part' => $part,
                'parts' => $parts,
                'filters' => $this->exporter->describeFilters($filters),
                'exported_by' => Auth::user()?->email,
            ]);
            Log::info('Store package exported', ['admin_id' => Auth::id(), 'file' => $name] + $built);
        } catch (\Throwable $e) {
            @unlink($path);
            Log::error('Store package export failed', ['admin_id' => Auth::id(), 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Could not build the package: '.$e->getMessage()], 500);
        }

        return response()->stream(function () use ($path, $keep) {
            readfile($path);
            if (! $keep) {
                @unlink($path);
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
            'Content-Length' => filesize($path),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /* ------------------------ kept packages ------------------------ */

    public function packages(): JsonResponse
    {
        $this->requireFull();

        return response()->json(['packages' => $this->listPackages()]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->requireFull();

        return response()->download($this->packagePath((string) $request->input('name')));
    }

    /** Deletes a package file this feature wrote — never anything else. */
    public function destroy(Request $request): JsonResponse
    {
        $this->requireFull();
        $path = $this->packagePath((string) $request->input('name'));
        @unlink($path);
        Log::info('Store package deleted', ['admin_id' => Auth::id(), 'file' => basename($path)]);

        return response()->json(['packages' => $this->listPackages()]);
    }

    /* ------------------------------ import ------------------------------ */

    /** Unpack a package (uploaded, or one kept on the server) and list what is in it. Writes no store data. */
    public function begin(Request $request): JsonResponse
    {
        $this->requireFull();
        set_time_limit(300);

        $request->validate([
            'package' => ['required_without:server_name', 'nullable', 'file', 'mimes:zip'],
            'server_name' => ['required_without:package', 'nullable', 'string'],
        ]);

        try {
            $path = $request->hasFile('package')
                ? $request->file('package')->getRealPath()
                : $this->packagePath((string) $request->input('server_name'));
            $session = $this->importer->begin($path);

            return response()->json($session + ['rows' => $this->importer->preview($session['token']), 'step_size' => self::STEP_SIZE]);
        } catch (\Throwable $e) {
            Log::warning('Store package could not be opened', ['admin_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function step(Request $request): JsonResponse
    {
        $this->requireFull();
        set_time_limit(300);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'indexes' => ['required', 'array', 'min:1', 'max:'.self::STEP_SIZE],
            'indexes.*' => ['integer', 'min:0'],
            'mode' => ['nullable', 'in:fill,overwrite'],
            'create_categories' => ['nullable', 'boolean'],
            'overwrite_media' => ['nullable', 'boolean'],
            'skip_media' => ['nullable', 'boolean'],
        ]);

        try {
            $options = [
                'mode' => $data['mode'] ?? 'fill',
                'create_categories' => (bool) ($data['create_categories'] ?? true),
                'overwrite_media' => (bool) ($data['overwrite_media'] ?? false),
                'skip_media' => (bool) ($data['skip_media'] ?? false),
            ];
            $results = array_map(fn (int $i) => $this->importer->applyStore($data['token'], $i, $options), $data['indexes']);
        } catch (\Throwable $e) {
            Log::error('Store package step failed', ['admin_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['results' => $results]);
    }

    public function finish(Request $request): JsonResponse
    {
        $this->requireFull();
        $token = (string) $request->input('token');
        $this->importer->cancel($token);
        Log::info('Store package import finished', ['admin_id' => Auth::id(), 'summary' => $request->input('summary')]);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->requireFull();
        $this->importer->cancel((string) $request->input('token'));

        return response()->json(['ok' => true]);
    }

    /* ------------------------------ helpers ------------------------------ */

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'category_ids' => collect(explode(',', (string) $request->input('category_ids', '')))->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->values()->all(),
            'sales_id' => $request->filled('sales_id') ? (int) $request->input('sales_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
        ];
    }

    /** @return list<array{name: string, size: int, modified: string}> */
    private function listPackages(): array
    {
        $out = [];
        foreach (glob(StoreMigrationExporter::packageDirectory().'/*.zip') ?: [] as $file) {
            $out[] = ['name' => basename($file), 'size' => (int) filesize($file), 'modified' => date('Y-m-d H:i', (int) filemtime($file))];
        }
        usort($out, fn ($a, $b) => strcmp($b['modified'], $a['modified']) ?: strcmp($b['name'], $a['name']));

        return $out;
    }

    /** A kept package by bare file name — no paths, so nothing outside the folder can be named. */
    private function packagePath(string $name): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name) === 1, 422, 'Invalid package name.');
        $path = StoreMigrationExporter::packageDirectory().'/'.$name;
        abort_unless(is_file($path), 404, 'That package is not on the server.');

        return $path;
    }
}
