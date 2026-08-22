<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Services\FacilityMigration\FacilityMigrationExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * The packages sitting on this server, ready to import.
 *
 * An export keeps its .zip in storage/app/facility-migration/ instead of
 * throwing it away after the download, so the same package can be imported
 * later from this screen without being uploaded back through the browser —
 * which for a multi-gigabyte package is often the only way it can happen at
 * all. The same directory is where a package copied over FTP lands, so both
 * arrive in one list.
 */
class AdminFacilityMigrationPackagesController extends BaseController
{
    /** What the import side is willing to read. */
    private const EXTENSIONS = ['zip', 'xlsx', 'xls', 'csv'];

    /**
     * The drop directory, created on first use.
     */
    public static function library(): string
    {
        $dir = storage_path('app/facility-migration');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * Every package on the server, newest first.
     */
    public function index(): JsonResponse
    {
        return response()->json(['packages' => $this->packages()]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        return response()->download($this->resolve($request->input('name')));
    }

    public function destroy(Request $request): JsonResponse
    {
        $path = $this->resolve($request->input('name'));
        @unlink($path);

        return response()->json(['packages' => $this->packages()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        $dir = self::library();
        $out = [];

        foreach (scandir($dir) ?: [] as $entry) {
            $path = $dir.'/'.$entry;
            if (! is_file($path) || ! $this->isPackageName($entry)) {
                continue;
            }

            $out[] = [
                'name' => $entry,
                'size' => filesize($path) ?: 0,
                'modified' => date('c', filemtime($path) ?: time()),
            ] + $this->manifest($path);
        }

        usort($out, fn ($a, $b) => strcmp($b['modified'], $a['modified']));

        return $out;
    }

    /**
     * What a package says about itself. Read straight out of the archive rather
     * than from a sidecar file, so a package copied here over FTP describes
     * itself exactly as one this site exported does.
     *
     * @return array<string, mixed>
     */
    private function manifest(string $path): array
    {
        $blank = ['generated_at' => null, 'counts' => null, 'options' => null, 'source' => null];

        if (! str_ends_with(strtolower($path), '.zip')) {
            return $blank;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return $blank;
        }

        $raw = $zip->getFromName(FacilityMigrationExporter::MANIFEST_ENTRY);
        $zip->close();

        $manifest = $raw ? json_decode($raw, true) : null;
        if (! is_array($manifest)) {
            return $blank;
        }

        return [
            'generated_at' => $manifest['generated_at'] ?? null,
            'counts' => $manifest['counts'] ?? null,
            'options' => $manifest['options'] ?? null,
            'source' => $manifest['source'] ?? null,
        ];
    }

    private function isPackageName(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * A name from the list, and nothing else — never a path that could walk out
     * of the drop directory.
     */
    private function resolve(mixed $name): string
    {
        $name = is_string($name) ? basename($name) : '';
        $path = self::library().'/'.$name;

        abort_unless($name !== '' && $this->isPackageName($name) && is_file($path), 404, 'No such package on this server.');

        return $path;
    }
}
