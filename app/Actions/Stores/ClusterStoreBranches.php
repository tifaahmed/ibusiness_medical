<?php

namespace App\Actions\Stores;

use App\Models\StoreBranch;
use Illuminate\Support\Facades\DB;

/**
 * Branch pins for the public "all branches" map on the stores directory,
 * merged by proximity — mirrors `App\Actions\Facilities\ClusterFacilityBranches`
 * exactly, over `StoreBranch` instead of `FacilityBranch`. See that class for
 * the reasoning behind the grid-bucket approach.
 */
class ClusterStoreBranches
{
    /** Approximate on-screen size of one merged pin's cell. */
    private const CELL_PX = 80;

    /**
     * @return list<array{latitude: float, longitude: float, count: int, branch: ?array<string, mixed>}>
     */
    public function handle(
        int $zoom,
        ?int $governorateId = null,
        ?int $cityId = null,
    ): array {
        $cell = $this->cellSizeForZoom($zoom);

        $clusters = StoreBranch::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($governorateId, fn ($q) => $q->where('governorate_id', $governorateId))
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
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

        $representatives = StoreBranch::with('store:id,slug,title')
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
                        'store' => $branch->store ? [
                            'slug' => $branch->store->slug,
                            'title' => $branch->store->title,
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
