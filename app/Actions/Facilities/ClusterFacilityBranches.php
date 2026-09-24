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
    /**
     * @return list<array{latitude: float, longitude: float, count: int, branch: ?array<string, mixed>}>
     */
    public function handle(
        int $zoom,
        ?int $governorateId = null,
        ?int $cityId = null,
        ?int $facilityTypeId = null,
    ): array {
        $precision = $this->precisionForZoom($zoom);

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
                DB::raw("ROUND(latitude, {$precision}) as grid_lat"),
                DB::raw("ROUND(longitude, {$precision}) as grid_lng"),
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

        $representatives = FacilityBranch::with('facility:id,slug,name')
            ->whereIn('id', $clusters->pluck('representative_id'))
            ->get()
            ->keyBy('id');

        return $clusters
            ->map(function ($cluster) use ($representatives) {
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
                        ] : null,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Grid cell size in decimal degrees, matched to Leaflet zoom levels:
     * zoomed out, a whole city collapses into one marker; zoomed all the way
     * in, only branches at almost the same address merge.
     */
    private function precisionForZoom(int $zoom): int
    {
        return match (true) {
            $zoom >= 16 => 5,  // ~1.1m
            $zoom >= 14 => 4,  // ~11m
            $zoom >= 11 => 3,  // ~110m
            $zoom >= 8 => 2,   // ~1.1km
            $zoom >= 5 => 1,   // ~11km
            default => 0,      // ~111km
        };
    }
}
