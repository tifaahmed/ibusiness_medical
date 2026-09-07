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
        $this->liftTimeLimit();

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
        $this->liftTimeLimit();

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
     * The same preview, delivered as it is built.
     *
     * Opening a whole-site package takes minutes — unzipping it, writing a file
     * per facility, then reading each one back against the rows this site
     * already holds — and {@see preview()} says nothing until all of it is
     * done. This answers newline-delimited JSON on the one request instead:
     * a "progress" line as each stage advances, then a single "result" line
     * carrying exactly what preview() returns.
     *
     * Progress rides the same request deliberately. A second endpoint polled
     * alongside would be the obvious shape, but the dev server runs one request
     * at a time, so every poll would queue behind the very work it is asking
     * about and the bar would sit at zero until the end.
     */
    public function previewStream(Request $request): StreamedResponse
    {
        // Before anything else: the whole point of this endpoint is a job too
        // long for PHP's default clock, and the body is written from a closure
        // that runs after this method returns — one call covers both.
        $this->liftTimeLimit();

        $request->validate([
            'package' => ['required_without:server_path', 'file', 'mimes:zip,json,xlsx,xls,csv'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
        ]);

        // The browser sends the id it is logging under, so both halves of one
        // attempt sit under the same trace in facility-migration.log.
        $trace = (string) ($request->header('X-Migration-Trace') ?: bin2hex(random_bytes(6)));
        $upload = $request->file('package');
        $openedAt = microtime(true);

        $this->trace($trace, 'server: preview stream requested', [
            'via' => $upload ? 'upload' : 'server_path',
            'file' => $upload?->getClientOriginalName(),
            'file_bytes' => $upload?->getSize(),
            'server_path' => $request->input('server_path'),
            'php' => $this->phpLimits(),
        ]);

        // Resolved before the stream opens: once the first byte is out the
        // status code is fixed, and a bad upload deserves a real HTTP error.
        // A spreadsheet is converted here too, which is the slowest thing this
        // endpoint does and the one place a fatal has already been seen.
        try {
            $packagePath = $this->packagePath($request);
        } catch (\Throwable $e) {
            $this->trace($trace, 'server: the package could not be resolved', [
                'error' => $e->getMessage(),
                'at' => $e->getFile().':'.$e->getLine(),
                'seconds' => round(microtime(true) - $openedAt, 2),
            ], 'error');

            throw $e;
        }

        $this->trace($trace, 'server: package resolved', [
            'path' => $packagePath,
            'bytes' => is_file($packagePath) ? filesize($packagePath) : null,
            'seconds' => round(microtime(true) - $openedAt, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        return response()->stream(function () use ($packagePath, $trace, $openedAt) {
            // What the shutdown guard below reports if the process never gets
            // to the end of this closure.
            $sent = ['lines' => 0, 'bytes' => 0, 'phase' => 'starting', 'result' => false, 'error' => false];

            /* A fatal — the execution-time cap, an exhausted memory_limit, a
               crash inside a extension — is not a Throwable, so the catch at
               the bottom never sees it. The process simply stops mid-body and
               the browser reports a connection that ended with no result in
               it. This is the only thing that leaves a trace of that. */
            register_shutdown_function(function () use (&$sent, $trace, $openedAt) {
                if ($sent['result'] || $sent['error']) {
                    return;
                }

                /* The likeliest reason for being here is an exhausted
                   memory_limit — and writing a log line allocates, so without
                   this the handler dies of the very thing it came to report.
                   Raising the cap during shutdown costs nothing: the request
                   is already over. */
                ini_set('memory_limit', '-1');

                $this->trace($trace, 'server: STREAM ENDED WITHOUT A RESULT', [
                    'phase' => $sent['phase'],
                    'lines_sent' => $sent['lines'],
                    'bytes_sent' => $sent['bytes'],
                    'seconds' => round(microtime(true) - $openedAt, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                    'client_aborted' => connection_aborted() === 1,
                    'connection_status' => connection_status(),
                    'last_php_error' => error_get_last(),
                ], 'error');
            });

            $emit = function (array $row) use (&$sent, $trace): void {
                $line = json_encode($row, JSON_UNESCAPED_UNICODE);

                /* json_encode answers false on so much as one byte that is not
                   UTF-8 — a name that came out of a spreadsheet in the wrong
                   encoding is enough. Echoing that would put a bare newline on
                   the wire, and a browser waiting for a result line it will
                   never get cannot tell that from a dead connection. */
                if ($line === false) {
                    $this->trace($trace, 'server: a line could not be encoded', [
                        'type' => $row['type'] ?? null,
                        'phase' => $row['phase'] ?? null,
                        'json_error' => json_last_error_msg(),
                    ], 'error');

                    $line = json_encode([
                        'type' => 'error',
                        'message' => 'The package holds text this server could not encode as JSON ('.json_last_error_msg().').',
                    ]);
                    $sent['error'] = true;
                }

                echo $line, "\n";
                $sent['lines']++;
                $sent['bytes'] += strlen($line) + 1;

                // Pushing each line out as it is written is what makes this a
                // progress report rather than one big answer at the end. Under
                // the test runner the response is captured by an output buffer
                // of its own, and flushing would empty it out from under the
                // assertions — there is no client waiting there anyway.
                if (PHP_SAPI === 'cli') {
                    return;
                }

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            // A package of thousands emits one line per row otherwise, which is
            // more bytes of progress than progress.
            $last = 0.0;
            $throttled = function (array $row) use ($emit, &$last): void {
                $now = microtime(true);
                if ($now - $last < 0.1) {
                    return;
                }
                $last = $now;
                $emit($row);
            };

            try {
                $sent['phase'] = 'extracting';
                $sessionStartedAt = microtime(true);

                // Logged once per phase rather than per row: the throttled rows
                // on the wire are for the progress bar, these are for the file.
                $loggedPhase = '';

                $session = $this->importer->beginSession(
                    $packagePath,
                    ['mode' => 'merge', 'dry_run' => false, 'skip_media' => false],
                    function (string $phase, int $processed, int $total) use ($throttled, &$sent, &$loggedPhase, $trace) {
                        $sent['phase'] = $phase;

                        if ($phase !== $loggedPhase) {
                            $loggedPhase = $phase;
                            $this->trace($trace, 'server: phase '.$phase, [
                                'total' => $total,
                                'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
                            ]);
                        }

                        $throttled([
                            'type' => 'progress',
                            'phase' => $phase,
                            'processed' => $processed,
                            'total' => $total,
                        ]);
                    },
                );

                $dir = storage_path('app/facility-migration/sessions/'.$session['token'].'/facilities');
                $facilities = [];
                $total = $session['total'];

                $this->trace($trace, 'server: session opened', [
                    'token' => $session['token'],
                    'total' => $total,
                    'counts' => $session['counts'],
                    'origin' => $session['origin'],
                    'seconds' => round(microtime(true) - $sessionStartedAt, 2),
                    'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
                ]);

                $sent['phase'] = 'reading';
                $readStartedAt = microtime(true);
                $emit(['type' => 'progress', 'phase' => 'reading', 'processed' => 0, 'total' => $total]);

                for ($i = 0; $i < $total; $i++) {
                    $file = sprintf('%s/%06d.json', $dir, $i);
                    if (is_file($file)) {
                        $facility = json_decode(file_get_contents($file), true) ?: [];
                        $facilities[] = array_merge(
                            $this->markBundledMedia($this->withExistingRows($facility), $session['token']),
                            ['_index' => $i]
                        );
                    } else {
                        $this->trace($trace, 'server: a facility file is missing', ['index' => $i, 'file' => $file], 'warning');
                    }

                    // Often enough to see where a long read stopped, rarely
                    // enough that the file stays readable.
                    if ($i % 50 === 0 || $i === $total - 1) {
                        $this->trace($trace, 'server: reading facilities', [
                            'read' => $i + 1,
                            'of' => $total,
                            'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
                            'client_aborted' => connection_aborted() === 1,
                        ]);
                    }

                    $throttled([
                        'type' => 'progress',
                        'phase' => 'reading',
                        'processed' => $i + 1,
                        'total' => $total,
                    ]);
                }

                $this->trace($trace, 'server: facilities read, building the result line', [
                    'facilities' => count($facilities),
                    'seconds' => round(microtime(true) - $readStartedAt, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                ]);

                $sent['phase'] = 'result';
                $emit([
                    'type' => 'result',
                    'token' => $session['token'],
                    'total' => $total,
                    'facilities' => $facilities,
                    'source' => $session['source'],
                    'generated_at' => $session['generated_at'],
                    'counts' => $session['counts'],
                    'origin' => $session['origin'],
                    'package_options' => $session['package_options'],
                ]);
                $sent['result'] = ! $sent['error'];

                $this->trace($trace, 'server: preview stream finished', [
                    'token' => $session['token'],
                    'lines_sent' => $sent['lines'],
                    'bytes_sent' => $sent['bytes'],
                    'seconds' => round(microtime(true) - $openedAt, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                ]);
            } catch (\Throwable $e) {
                Log::error('Facility migration preview failed', ['error' => $e->getMessage()]);
                $this->trace($trace, 'server: preview stream threw', [
                    'phase' => $sent['phase'],
                    'error' => $e->getMessage(),
                    'at' => $e->getFile().':'.$e->getLine(),
                    'seconds' => round(microtime(true) - $openedAt, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                ], 'error');

                // The status line went out with the first byte, so the failure
                // has to travel in the body like everything else.
                $sent['error'] = true;
                $emit(['type' => 'error', 'message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-store',
            // Tells nginx not to sit on the body until the handler returns.
            'X-Accel-Buffering' => 'no',
            'X-Migration-Trace' => $trace,
        ]);
    }

    /**
     * Take a line the browser logged and put it in the same file, under the
     * same trace, as the server's own.
     *
     * The two halves of a failed import each know only half of it: the browser
     * that the connection ended, the server that it stopped writing. Read in
     * one file in order, they say between them where it stopped.
     */
    public function clientLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trace' => ['nullable', 'string', 'max:64'],
            'event' => ['required', 'string', 'max:200'],
            'context' => ['nullable', 'array'],
            'level' => ['nullable', 'in:info,warning,error'],
        ]);

        $this->trace(
            $validated['trace'] ?? 'browser',
            $validated['event'],
            $validated['context'] ?? [],
            $validated['level'] ?? 'info',
        );

        return response()->json(['logged' => true]);
    }

    /**
     * Take PHP's execution clock off a request that opens a package.
     *
     * Reading a whole-site export — converting a spreadsheet, unzipping it,
     * writing a file per facility — runs for minutes, and PHP's default cap is
     * 30 seconds. Worse, blowing it is a *fatal*: no exception is thrown, so
     * nothing here catches it, the process simply stops mid-response, and the
     * browser is left reporting a connection that ended before the result
     * arrived. That is not a hypothetical — laravel.log has it twice, both
     * inside PhpSpreadsheet, from a package exported in 215 parts.
     *
     * Note the cap does not come from the CLI's own settings, which is what
     * makes it easy to miss: `php artisan serve` runs the cli-server SAPI, and
     * that one honours php.ini's max_execution_time where the plain CLI forces
     * it to 0. So the limit bites when the app is served and never when the
     * same package is imported through the console command.
     *
     * Removing it here is safe because these endpoints already report their own
     * progress — the streaming preview line by line, the stepped import a chunk
     * per request — so a run that has genuinely stalled is visible without the
     * clock, and a real deployment still has the server's own ceiling
     * (fpm's request_terminate_timeout, nginx's read timeout) above this.
     */
    private function liftTimeLimit(): void
    {
        // Hosts sometimes put this on disable_functions; nothing is lost by
        // carrying on with the default cap if so.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    /**
     * One line in storage/logs/facility-migration.log.
     */
    private function trace(string $trace, string $message, array $context = [], string $level = 'info'): void
    {
        try {
            Log::channel('migration')->log($level, $message, ['trace' => $trace] + $context);
        } catch (\Throwable) {
            // Diagnostics must never be the thing that breaks the import.
        }
    }

    /**
     * The settings that decide whether a long import survives at all — the two
     * fatals this endpoint has actually died of are a spent execution-time cap
     * and an exhausted memory_limit, and neither leaves anything else behind.
     *
     * @return array<string, mixed>
     */
    private function phpLimits(): array
    {
        return [
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'output_buffering' => ini_get('output_buffering'),
            'ob_level' => ob_get_level(),
        ];
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
        // Opens the same session the preview does, and unpacks the same package.
        $this->liftTimeLimit();

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
        // Writes a whole package back out, media and all.
        $this->liftTimeLimit();

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
                return $this->convertSpreadsheet($request, $file->getRealPath(), $file->getClientOriginalName());
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
            return $this->convertSpreadsheet($request, $resolved, basename($resolved));
        }

        return $resolved;
    }

    /**
     * Turn a spreadsheet into a migration zip, timed.
     *
     * Reading a workbook is by far the slowest thing an import does and the one
     * step that has died on PHP's execution-time cap — a fatal, so the request
     * simply stops and the browser is left saying the connection ended. These
     * two lines say how long it took, or that it never got to the second one.
     */
    private function convertSpreadsheet(Request $request, string $path, string $name): string
    {
        $trace = (string) ($request->header('X-Migration-Trace') ?: 'no-trace');
        $startedAt = microtime(true);

        $this->trace($trace, 'server: converting the spreadsheet', [
            'name' => $name,
            'bytes' => is_file($path) ? filesize($path) : null,
            'php' => $this->phpLimits(),
        ]);

        $converted = app(XlsxToMigrationZip::class)->convert($path);

        $this->trace($trace, 'server: spreadsheet converted', [
            'name' => $name,
            'zip' => $converted,
            'zip_bytes' => is_file($converted) ? filesize($converted) : null,
            'seconds' => round(microtime(true) - $startedAt, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        return $converted;
    }
}
