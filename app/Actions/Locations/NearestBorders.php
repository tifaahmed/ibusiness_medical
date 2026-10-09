<?php

namespace App\Actions\Locations;

use App\Models\City;
use App\Models\Governorate;
use App\Support\GeoJson;
use Illuminate\Support\Facades\Cache;

/**
 * The few governorates or cities whose borders lie closest to a point, with
 * their geometry, for the storefront map's border overlay.
 *
 * "Closest" is the distance to the BORDER, and zero when the point is inside
 * it — so standing in Giza, Giza comes first, then its neighbours. Borders are
 * few enough (27 governorates, ~385 cities, under 1MB decoded) to rank in PHP
 * with no spatial database; the decoded geometry is cached, and only the
 * winners' names are read fresh, so the answer follows the request's locale.
 */
class NearestBorders
{
    public const LEVELS = ['governorate', 'city'];

    /**
     * @return list<array{id: int, name: ?string, distance_km: float, geometry?: array<string, mixed>}>
     */
    public function handle(string $level, float $lat, float $lng, int $limit = 4, ?int $governorateId = null, bool $withGeometry = true): array
    {
        $rows = $this->borders($level);

        /* Only the cities of one governorate — a city picker inside a chosen governorate. */
        if ($level === 'city' && $governorateId !== null) {
            $rows = array_intersect_key($rows, array_flip(City::query()->where('governorate_id', $governorateId)->pluck('id')->all()));
        }

        $ranked = [];

        foreach ($rows as $id => $geometry) {
            $ranked[$id] = $this->distanceKm($geometry, $lat, $lng);
        }

        asort($ranked);
        $ids = array_slice(array_keys($ranked), 0, max(1, $limit));

        if ($ids === []) {
            return [];
        }

        $model = $level === 'city' ? City::class : Governorate::class;
        $names = $model::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');

        return array_map(fn (int $id) => [
            'id' => $id,
            'name' => $names->get($id)?->name,
            'distance_km' => round($ranked[$id], 1),
            ...($withGeometry ? ['geometry' => $rows[$id]] : []),
        ], $ids);
    }

    /**
     * @return array<int, array<string, mixed>> id => GeoJSON geometry
     */
    private function borders(string $level): array
    {
        return Cache::remember("nearest-borders:{$level}", now()->addHour(), function () use ($level) {
            $model = $level === 'city' ? City::class : Governorate::class;
            $out = [];

            foreach ($model::query()->whereNotNull('boundary')->get(['id', 'boundary']) as $row) {
                if (is_array($row->boundary) && isset($row->boundary['coordinates'])) {
                    $out[$row->id] = $row->boundary;
                }
            }

            return $out;
        });
    }

    /** Kilometres from the point to the border; 0 when it is inside. */
    private function distanceKm(array $geometry, float $lat, float $lng): float
    {
        if (GeoJson::contains($geometry, $lng, $lat)) {
            return 0.0;
        }

        $rings = match ($geometry['type'] ?? null) {
            'Polygon' => $geometry['coordinates'],
            'MultiPolygon' => array_merge(...$geometry['coordinates']),
            default => [],
        };

        // Equirectangular: plenty for ranking neighbours inside one country.
        $kmPerLat = 110.574;
        $kmPerLng = 111.320 * cos(deg2rad($lat));
        $best = INF;

        foreach ($rings as $ring) {
            $count = count($ring);

            for ($i = 0; $i < $count - 1; $i++) {
                $ax = ($ring[$i][0] - $lng) * $kmPerLng;
                $ay = ($ring[$i][1] - $lat) * $kmPerLat;
                $bx = ($ring[$i + 1][0] - $lng) * $kmPerLng;
                $by = ($ring[$i + 1][1] - $lat) * $kmPerLat;

                $dx = $bx - $ax;
                $dy = $by - $ay;
                $len = $dx * $dx + $dy * $dy;
                $t = $len > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $len)) : 0;
                $d = hypot($ax + $t * $dx, $ay + $t * $dy);

                if ($d < $best) {
                    $best = $d;
                }
            }
        }

        return is_finite($best) ? $best : 99999.0;
    }
}
