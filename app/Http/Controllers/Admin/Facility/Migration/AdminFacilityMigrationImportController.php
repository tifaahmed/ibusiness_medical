<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Services\FacilityMigration\FacilityMigrationImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'package' => ['required_without:server_path', 'file'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
        ]);

        try {
            return response()->json($this->importer->inspect($this->packagePath($request)));
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
            'package' => ['required_without:server_path', 'file'],
            'server_path' => ['required_without:package', 'nullable', 'string'],
            'mode' => ['required', 'in:fresh,merge'],
            'dry_run' => ['nullable', 'boolean'],
            'skip_media' => ['nullable', 'boolean'],
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
     * Abandon a session without importing the rest.
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string']]);
        $this->importer->endSession($validated['token']);

        return response()->json(['message' => 'Import session discarded.']);
    }

    /**
     * The package either arrives as an upload or is already on the server —
     * the latter being the way around PHP's upload limits for big archives.
     */
    private function packagePath(Request $request): string
    {
        if ($request->hasFile('package')) {
            return $request->file('package')->getRealPath();
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

        return $resolved;
    }
}
