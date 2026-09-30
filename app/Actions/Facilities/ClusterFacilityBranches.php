<?php

namespace App\Actions\Facilities;

use App\Models\FacilityBranch;
use Illuminate\Support\Facades\DB;

/**
 * Branch pins for the public "all branches" map, merged by proximity so a
 * directory with thousands of branches never hands the browser thousands of
 * markers — or the server thousands of rows to hydrate.
 *
 * Branches are grouped on a grid whose cell size follows the map's own zoom
 * level ({@see precisionForZoom()}): wide cells zoomed out, narrow ones
 * zoomed in, the way any tile-based cluster works. The grouping happens in
 * SQL (`GROUP BY` on the rounded coordinate), so the row count crossing the
 * wire is the number of DISTINCT cells, never the number of branches — that
 * is what actually keeps this cheap, not anything the browser does with the
 * answer afterwards.
 *
 * One representative branch is fetched per cluster (the lowest id in the
 * cell) purely so a popup has a name and a link to show; the merged `count`
 * is what tells the visitor there is more than one branch at that pin.
 */
class ClusterFacilityBranches
{
    /** Approximate on-screen size of one merged pin's cell. */
    private const CELL_PX = 80;

    /** From this zoom a lone branch's pin becomes its facility's logo. */
    private const LOGO_MIN_ZOOM = 13;

    /**
     * @return list<array{latitude: float, longitude: float, count: int, branch: ?array<string, mixed>}>
     */
    public function handle(
        int $zoom,
        ?int $governorateId = null,
        ?int $cityId = null,
        ?int $facilityTypeId = null,
    ): array {
        $cell = $this->cellSizeForZoom($zoom);

        $clusters = FacilityBranch::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($governorateId, fn ($q) => $q->where('governorate_id', $governorateId))
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
            ->when($facilityTypeId, fn ($q) => $q->whereHas(
                'facility',
                fn ($f) => $f->where('facility_type_id', $facilityTypeId),
            ))
            ->select([
                DB::raw("FLOOR(latitude / {$cell}) as grid_lat"),
                DB::raw("FLOOR(longitude / {$cell}) as grid_lng"),
                DB::raw('COUNT(*) as branch_count'),
                DB::raw('AVG(latitude) as avg_lat'),
                DB::raw('AVG(longitude) as avg_lng'),
                DB::raw('MIN(id) as representative_id'),
            ])
            ->groupBy('grid_lat', 'grid_lng')
            ->get();

        if ($clusters->isEmpty()) {
            return [];
        }

        $withLogos = $zoom >= self::LOGO_MIN_ZOOM;

        // Logos only where a pin can show one (close in), and with the media
        // eager-loaded so a thousand pins do not become a thousand queries.
        $representatives = FacilityBranch::with($withLogos ? ['facility:id,slug,name', 'facility.media'] : ['facility:id,slug,name'])
            ->whereIn('id', $clusters->pluck('representative_id'))
            ->get()
            ->keyBy('id');

        return $clusters
            ->map(function ($cluster) use ($representatives, $withLogos) {
                $branch = $representatives->get((int) $cluster->representative_id);

                return [
                    'latitude' => round((float) $cluster->avg_lat, 7),
                    'longitude' => round((float) $cluster->avg_lng, 7),
                    'count' => (int) $cluster->branch_count,
                    'branch' => $branch ? [
                        'id' => $branch->id,
                        'slug' => $branch->slug,
                        'name' => $branch->name,
                        'facility' => $branch->facility ? [
                            'slug' => $branch->facility->slug,
                            'name' => $branch->facility->name,
                            'logo' => $withLogos && (int) $cluster->branch_count === 1
                                ? ($branch->facility->logo ?: null)
                                : null,
                        ] : null,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Grid cell size in decimal degrees, sized in SCREEN pixels rather than
     * fixed decimal places: a Leaflet world is 256 * 2^zoom px wide, so a
     * cell of CELL_PX pixels is CELL_PX * 360 / (256 * 2^zoom) degrees. Any
     * two pins closer than about a cell on screen therefore merge at EVERY
     * zoom, instead of the old decimal rounding that left pins 100m-1km apart
     * unmerged across zoom 11-15. Capped at zoom 18 so branches at the very
     * same address still merge when zoomed all the way in.
     */
    private function cellSizeForZoom(int $zoom): string
    {
        $degrees = self::CELL_PX * 360 / (256 * (2 ** min(max($zoom, 0), 18)));

        return number_format($degrees, 8, '.', '');
    }
}
