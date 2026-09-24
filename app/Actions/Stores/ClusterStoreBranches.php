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
    /**
     * @return list<array{latitude: float, longitude: float, count: int, branch: ?array<string, mixed>}>
     */
    public function handle(
        int $zoom,
        ?int $governorateId = null,
        ?int $cityId = null,
    ): array {
        $precision = $this->precisionForZoom($zoom);

        $clusters = StoreBranch::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($governorateId, fn ($q) => $q->where('governorate_id', $governorateId))
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
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
     * Grid cell size in decimal degrees, matched to Leaflet zoom levels — same
     * table as `ClusterFacilityBranches::precisionForZoom()`.
     */
    private function precisionForZoom(int $zoom): int
    {
        return match (true) {
            $zoom >= 16 => 5,
            $zoom >= 14 => 4,
            $zoom >= 11 => 3,
            $zoom >= 8 => 2,
            $zoom >= 5 => 1,
            default => 0,
        };
    }
}
