<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Services\FacilityMigration\FacilityMigrationImporter;
use App\Services\FacilityMigration\XlsxToMigrationZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stepped restore of a migration package.
 *
 * The browser never does the whole job in one request: it opens a session
 * (upload once, or point at a file already sitting on the server), then calls
 * `step` repeatedly until `done` comes back true, then `finish`. Each step is a
 * short request handling a handful of facilities, so nothing times out and the
 * progress bar keeps moving.
 */
class AdminFacilityMigrationImportController extends BaseController
{
    /** Facilities handled per step when the caller does not say. */
    private const DEFAULT_CHUNK = 5;

    public function __construct(private FacilityMigrationImporter $importer) {}

    /**
     * Report what a package contains. Writes nothing.
     */
    public function inspect(Request $request): JsonResponse
    {
        $request->validate([
            'package' => ['required_without:server_path', 'file', 'mimes:zip,json,xlsx,xls,csv'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
        ]);

        try {
            return response()->json($this->importer->inspect($this->packagePath($request)));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unpack the package and return all facilities for preview/editing.
     * Creates an import session so the operator can edit data before importing.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'package' => ['required_without:server_path', 'file', 'mimes:zip,json,xlsx,xls,csv'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
        ]);

        try {
            $session = $this->importer->beginSession($this->packagePath($request), [
                'mode' => 'merge',
                'dry_run' => false,
                'skip_media' => false,
            ]);

            $dir = storage_path('app/facility-migration/sessions/'.$session['token'].'/facilities');
            $facilities = [];
            $total = $session['total'];
            for ($i = 0; $i < $total; $i++) {
                $file = sprintf('%s/%06d.json', $dir, $i);
                if (is_file($file)) {
                    $facility = json_decode(file_get_contents($file), true) ?: [];
                    $facilities[] = array_merge(
                        $this->markBundledMedia($this->withExistingRows($facility), $session['token']),
                        ['_index' => $i]
                    );
                }
            }

            return response()->json([
                'token' => $session['token'],
                'total' => $total,
                'facilities' => $facilities,
                'source' => $session['source'],
                'generated_at' => $session['generated_at'],
                'counts' => $session['counts'],
                // A package this site's own export built carries a stable id and
                // slug for every row, so the screen can stop insisting a human
                // tells two same-named branches apart.
                'origin' => $session['origin'],
                'package_options' => $session['package_options'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Facility migration preview failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Stream one image out of an open session's package.
     *
     * The preview shows what a package carries before a single row is written,
     * so its pictures have no model, no disk and no URL yet — they exist only
     * inside the session's extraction, and this is the one way to look at them.
     * The session ends and they are gone with it.
     */
    public function media(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'path' => ['required', 'string'],
        ]);

        try {
            $path = $this->importer->sessionMediaPath($validated['token'], $validated['path']);
        } catch (\Throwable) {
            $path = null;
        }

        abort_if($path === null, 404, 'That image is not in this package.');

        return response()->file($path, [
            // Private to this operator's open session, and gone when it closes.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Re-run the "does this site already have it?" match for one facility the
     * operator has edited on the preview screen.
     *
     * The new/already-here badges the preview first paints come from the names
     * the package carried. Rename a branch to match a row this site keeps and
     * the import would then update that row rather than insert a new one — but
     * the badge, a snapshot, would still say "new". This recomputes it against
     * the edited payload so the screen keeps telling the truth. Writes nothing.
     */
    public function rematch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        try {
            $marked = $this->withExistingRows($validated['data']);

            return response()->json([
                'facility' => $marked['_existing'] ?? null,
                'missing_branches' => $marked['_missing_branches'] ?? [],
                'missing_managers' => $marked['_missing_managers'] ?? [],
                'branches' => array_map(
                    fn ($row) => is_array($row) ? ($row['_existing'] ?? null) : null,
                    array_values($marked['branches'] ?? [])
                ),
                'managers' => array_map(
                    fn ($row) => is_array($row) ? ($row['_existing'] ?? null) : null,
                    array_values($marked['managers'] ?? [])
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Say, of every image a facility payload names, whether its bytes are
     * actually in the package.
     *
     * A row can name a picture the archive does not carry — a data-only package
     * from an older version, or a file that had already gone missing on the
     * source host. The screen has to know which is which, or it would paint a
     * broken thumbnail and offer to import a picture that is not there.
     *
     * @param  array<string, mixed>  $facility
     * @return array<string, mixed>
     */
    private function markBundledMedia(array $facility, string $token): array
    {
        $mark = function (array $rows) use ($token) {
            foreach ($rows as $i => $row) {
                $path = is_array($row) ? ($row['package_path'] ?? null) : null;
                $rows[$i]['_bundled'] = $path !== null
                    && $this->importer->sessionMediaPath($token, $path) !== null;
            }

            return $rows;
        };

        if (is_array($facility['media'] ?? null)) {
            $facility['media'] = $mark($facility['media']);
        }

        foreach (['offers'] as $relation) {
            foreach ($facility[$relation] ?? [] as $i => $offer) {
                if (is_array($offer['media'] ?? null)) {
                    $facility[$relation][$i]['media'] = $mark($offer['media']);
                }
            }
        }

        foreach ($facility['branches'] ?? [] as $bi => $branch) {
            foreach ($branch['offers'] ?? [] as $oi => $offer) {
                if (is_array($offer['media'] ?? null)) {
                    $facility['branches'][$bi]['offers'][$oi]['media'] = $mark($offer['media']);
                }
            }
        }

        return $facility;
    }

    /**
     * Save an edited facility back to the session before importing.
     */
    public function edit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'data' => ['required', 'array'],
        ]);

        try {
            $this->importer->writeFacilityFile(
                $validated['token'],
                (int) $validated['index'],
                $validated['data']
            );

            return response()->json(['message' => 'Facility updated.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Apply the mode and switches chosen on the preview screen to the session
     * that preview opened, before the first chunk runs.
     */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'mode' => ['required', 'in:fresh,merge'],
            'dry_run' => ['nullable', 'boolean'],
            'skip_media' => ['nullable', 'boolean'],
            'prune_missing' => ['nullable', 'boolean'],
        ]);

        if ($validated['mode'] === 'fresh' && ! $request->boolean('dry_run') && ! $request->boolean('confirm_wipe')) {
            return response()->json([
                'message' => 'Fresh mode deletes all existing facilities, branches and their images. Re-send with confirm_wipe=true.',
            ], 422);
        }

        try {
            $this->importer->updateSessionOptions($validated['token'], [
                'mode' => $validated['mode'],
                'dry_run' => $request->boolean('dry_run'),
                'skip_media' => $request->boolean('skip_media'),
                'prune_missing' => $request->boolean('prune_missing'),
            ]);

            return response()->json(['message' => 'Import settings saved.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unpack the package and hand back a token to step through.
     */
    public function begin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package' => ['required_without:server_path', 'file', 'mimes:zip,json,xlsx,xls,csv'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
            'mode' => ['required', 'in:fresh,merge'],
            'dry_run' => ['nullable', 'boolean'],
            'skip_media' => ['nullable', 'boolean'],
            'prune_missing' => ['nullable', 'boolean'],
            'media_path' => ['nullable', 'string'],
        ]);

        // "fresh" deletes every existing facility and its image files, which no
        // transaction rollback can undo — so the caller has to say it twice.
        if ($validated['mode'] === 'fresh' && ! $request->boolean('dry_run') && ! $request->boolean('confirm_wipe')) {
            return response()->json([
                'message' => 'Fresh mode deletes all existing facilities, branches and their images. Re-send with confirm_wipe=true.',
            ], 422);
        }

        try {
            $session = $this->importer->beginSession($this->packagePath($request), [
                'mode' => $validated['mode'],
                'dry_run' => $request->boolean('dry_run'),
                'skip_media' => $request->boolean('skip_media'),
                'prune_missing' => $request->boolean('prune_missing'),
                'media_path' => $validated['media_path'] ?? null,
            ]);

            Log::info('Facility migration session opened', [
                'token' => $session['token'],
                'mode' => $session['mode'],
                'total' => $session['total'],
            ]);

            return response()->json($session);
        } catch (\Throwable $e) {
            Log::error('Facility migration session failed to open', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Import the next few facilities.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            return response()->json($this->importer->processChunk(
                $validated['token'],
                (int) ($validated['limit'] ?? self::DEFAULT_CHUNK)
            ));
        } catch (\Throwable $e) {
            Log::error('Facility migration step failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Close the session and return the final tally.
     */
    public function finish(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string']]);

        try {
            $summary = $this->importer->summarise($validated['token']);
            $this->importer->endSession($validated['token']);

            Log::info('Facility migration import finished', [
                'mode' => $summary['mode'],
                'dry_run' => $summary['dry_run'],
                'stats' => $summary['stats'],
            ]);

            return response()->json($summary);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Hand the review back as a package.
     *
     * Working through a whole-site package is hours of picking cities, renaming
     * branches and dropping images, and none of it used to outlive the session.
     * This writes the edited rows out as a file the operator keeps — to put the
     * job down and come back to it, or to hand it to somebody else — and it
     * imports exactly like the package it came from.
     */
    public function exportSession(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'format' => ['nullable', 'in:zip,xlsx'],
        ]);

        $includeMedia = ! $request->has('include_media') || $request->boolean('include_media');
        $format = $validated['format'] ?? ($includeMedia ? 'zip' : 'xlsx');

        $path = $this->importer->exportSession($validated['token'], [
            'format' => $format,
            'include_media' => $includeMedia,
            'include_branches' => ! $request->has('include_branches') || $request->boolean('include_branches'),
            'include_managers' => ! $request->has('include_managers') || $request->boolean('include_managers'),
            'include_offers' => ! $request->has('include_offers') || $request->boolean('include_offers'),
        ]);

        $filename = sprintf('facility-migration-reviewed-%s.%s', now()->format('Y-m-d_His'), $format);

        return response()->stream(function () use ($path) {
            $out = fopen('php://output', 'wb');
            $in = fopen($path, 'rb');
            // A reviewed package carries the same images the original did, so it
            // is piped out in chunks rather than read into memory.
            while (! feof($in)) {
                fwrite($out, fread($in, 1024 * 1024));
                flush();
            }
            fclose($in);
            fclose($out);
            // The session owns the originals; this copy was only ever the download.
            @unlink($path);
        }, 200, [
            'Content-Type' => $format === 'zip'
                ? 'application/zip'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Abandon a session without importing the rest.
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string']]);
        $this->importer->endSession($validated['token']);

        return response()->json(['message' => 'Import session discarded.']);
    }

    /**
     * Mark up a facility payload with what this site already holds for it: the
     * facility row a merge would land on, and the same for each of its
     * branches and managers. All come back under `_existing` — a key the screen
     * strips again before sending its edits back, so it never reaches the
     * session files or the import itself.
     *
     * @param  array<string, mixed>  $facility
     * @return array<string, mixed>
     */
    private function withExistingRows(array $facility): array
    {
        $target = $this->importer->describeTarget($facility);

        $facility['_existing'] = $target['facility'];
        // The other side of the ledger: rows this site holds that the package
        // never names. A pruning merge deletes exactly these, so the screen can
        // paint them red before anybody commits to it.
        $facility['_missing_branches'] = $target['missing_branches'];
        $facility['_missing_managers'] = $target['missing_managers'];
        $facility = $this->seedUnmentionedColumns($facility, $target['facility']);

        foreach (['branches', 'managers'] as $relation) {
            if (! is_array($facility[$relation] ?? null)) {
                continue;
            }
            $facility[$relation] = array_values($facility[$relation]);
            foreach ($facility[$relation] as $i => $row) {
                if (is_array($row)) {
                    $facility[$relation][$i]['_existing'] = $target[$relation][$i] ?? null;
                }
            }
        }

        return $facility;
    }

    /**
     * A spreadsheet need not carry a Sales or Discount % column at all, and the
     * import leaves what it does not mention alone. The preview screen has no
     * such third state — its picker and its number box always say something —
     * so the columns the package skipped start on the value the facility holds
     * today. What the screen shows is then what the import writes, and the
     * operator can still change it deliberately.
     *
     * @param  array<string, mixed>  $facility
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function seedUnmentionedColumns(array $facility, ?array $existing): array
    {
        if (! array_key_exists('sales', $facility)) {
            $sales = $existing['sales'] ?? null;
            $facility['sales'] = $sales
                ? ['id' => $sales['id'], 'name' => ['en' => $sales['label'], 'ar' => $sales['label']]]
                : null;
        }

        if (! array_key_exists('discount_percent', $facility)) {
            $facility['discount_percent'] = $existing['discount_percent'] ?? null;
        }

        return $facility;
    }

    /**
     * The package either arrives as an upload or is already on the server —
     * the latter being the way around PHP's upload limits for big archives.
     */
    private function packagePath(Request $request): string
    {
        if ($request->hasFile('package')) {
            $file = $request->file('package');
            $ext = strtolower($file->getClientOriginalExtension());

            // Convert spreadsheet to migration zip on the fly
            if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                $converter = app(XlsxToMigrationZip::class);

                return $converter->convert($file->getRealPath());
            }

            return $file->getRealPath();
        }

        $root = realpath(storage_path('app/facility-migration'));
        if (! $root) {
            abort(422, 'storage/app/facility-migration does not exist yet — create it and upload the package there.');
        }

        $candidate = $request->input('server_path');
        // Only ever read from inside the drop directory, whatever was typed.
        $resolved = realpath(str_starts_with($candidate, '/') ? $candidate : $root.'/'.$candidate);

        if (! $resolved || ! is_file($resolved) || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            abort(422, 'The package must be a file inside storage/app/facility-migration.');
        }

        // Convert spreadsheet server files too
        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $converter = app(XlsxToMigrationZip::class);

            return $converter->convert($resolved);
        }

        return $resolved;
    }
}
