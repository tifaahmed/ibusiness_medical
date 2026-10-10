<?php

namespace App\Http\Controllers\Api\V1\Mobile\Concerns;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * "Nearest branch" maths shared by the facility and store lists.
 */
trait NearestBranch
{
    /** Radius steps a visitor may pick, in km — same set as the web directory. */
    protected const RADIUS_STEPS_KM = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

    /**
     * One row per owner that has a geocoded branch: its NEAREST branch's
     * distance in km. Joined once, instead of a Haversine call per row.
     */
    protected function nearestBranchKm(string $table, string $ownerColumn, float $lat, float $lng): QueryBuilder
    {
        return DB::table($table)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy($ownerColumn)
            ->selectRaw("{$ownerColumn}, MIN(".self::distanceSql().') as distance_km', [$lat, $lng, $lat]);
    }

    /** Haversine over `latitude` / `longitude`, bindings: lat, lng, lat. */
    protected static function distanceSql(): string
    {
        return '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        ))))';
    }

    protected static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }

    /**
     * The branch a card should lead with out of `$branches`: the nearest to the
     * point if there is one, else the first matching the chosen place, else the
     * first geocoded one, else simply the first.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $branches
     */
    protected function leadBranch($branches, ?float $lat, ?float $lng, ?int $governorateId, ?int $cityId)
    {
        if ($branches->isEmpty()) {
            return null;
        }

        if ($lat !== null && $lng !== null) {
            $geocoded = $branches->filter(fn ($b) => $b->latitude !== null && $b->longitude !== null);

            if ($geocoded->isNotEmpty()) {
                return $geocoded->sortBy(fn ($b) => self::distanceKm($lat, $lng, (float) $b->latitude, (float) $b->longitude))->first();
            }
        }

        if ($cityId !== null) {
            $hit = $branches->firstWhere('city_id', $cityId);
        }

        if (empty($hit) && $governorateId !== null) {
            $hit = $branches->firstWhere('governorate_id', $governorateId);
        }

        return $hit ?? $branches->first();
    }
}
