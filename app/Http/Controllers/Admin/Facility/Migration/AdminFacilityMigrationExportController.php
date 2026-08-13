<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Services\FacilityMigration\FacilityMigrationExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a migration package (data + image files) for the facilities matching
 * the current filters.
 *
 * A whole site can be far too large for one download, so the export can be cut
 * into parts: `plan` says how many parts a given part size needs, and each call
 * to the download endpoint with `part=N` produces a self-contained package for
 * that slice. Every part imports independently.
 */
class AdminFacilityMigrationExportController extends BaseController
{
    public function __construct(private FacilityMigrationExporter $exporter) {}

    /**
     * How many facilities match, and how many parts that is at this part size.
     */
    public function plan(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $total = $this->exporter->countMatching($filters);
        $perPart = max(1, (int) $request->input('per_part', 25));

        return response()->json([
            'total' => $total,
            'per_part' => $perPart,
            'parts' => $total > 0 ? (int) ceil($total / $perPart) : 0,
            'filters' => $filters,
        ]);
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $includeMedia = ! $request->has('include_media') || $request->boolean('include_media');

        // part is 1-based; without it the whole matching set goes in one package.
        $perPart = $request->filled('per_part') ? max(1, (int) $request->input('per_part')) : null;
        $part = $request->filled('part') ? max(1, (int) $request->input('part')) : null;
        $filters = $this->filters($request);

        $totalParts = null;
        if ($perPart !== null && $part !== null) {
            $total = $this->exporter->countMatching($filters);
            $totalParts = $total > 0 ? (int) ceil($total / $perPart) : 1;
        }

        $path = $this->exporter->build([
            'include_media_files' => $includeMedia,
            'include_offers' => ! $request->has('include_offers') || $request->boolean('include_offers'),
            'filters' => $filters,
            'offset' => ($perPart !== null && $part !== null) ? ($part - 1) * $perPart : null,
            'limit' => $perPart,
        ]);

        $filename = $this->exporter->filename($includeMedia, $part, $totalParts);

        return response()->stream(function () use ($path) {
            $out = fopen('php://output', 'wb');
            $in = fopen($path, 'rb');
            // Packages can run to gigabytes once images are bundled, so the file
            // is piped out in chunks instead of being read into memory.
            while (! feof($in)) {
                fwrite($out, fread($in, 1024 * 1024));
                flush();
            }
            fclose($in);
            fclose($out);
            @unlink($path);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'search' => $request->input('search'),
            'slug' => $request->input('slug'),
            'facility_type_id' => $request->input('facility_type_id'),
            'sales_id' => $request->input('sales_id'),
            'governorate_id' => $request->input('governorate_id'),
            'created_from' => $request->input('created_from'),
            'created_to' => $request->input('created_to'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
