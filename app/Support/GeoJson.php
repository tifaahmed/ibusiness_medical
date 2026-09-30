<?php

namespace App\Support;

/**
 * Just enough GeoJSON geometry (Polygon / MultiPolygon, [lng, lat]) to ask
 * "where is this border's middle" and "is this point inside that border"
 * without a spatial database.
 */
class GeoJson
{
    /**
     * The area-weighted centre of the largest polygon, or null for no usable ring.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function centroid(?array $geometry): ?array
    {
        $best = null;
        $bestArea = -1.0;
        foreach (self::polygons($geometry) as $polygon) {
            $area = abs(self::signedArea($polygon[0] ?? []));
            if ($area > $bestArea) {
                $best = $polygon[0];
                $bestArea = $area;
            }
        }

        if ($best === null || count($best) < 4) {
            return null;
        }

        $twiceArea = $cx = $cy = 0.0;
        for ($i = 0, $n = count($best) - 1; $i < $n; $i++) {
            $cross = $best[$i][0] * $best[$i + 1][1] - $best[$i + 1][0] * $best[$i][1];
            $twiceArea += $cross;
            $cx += ($best[$i][0] + $best[$i + 1][0]) * $cross;
            $cy += ($best[$i][1] + $best[$i + 1][1]) * $cross;
        }

        // A degenerate (zero-area) ring has no weighted centre: use its first corner.
        return abs($twiceArea) < 1e-12 ? [(float) $best[0][0], (float) $best[0][1]] : [$cx / (3 * $twiceArea), $cy / (3 * $twiceArea)];
    }

    /**
     * Whether the point lies inside the geometry (holes excluded).
     */
    public static function contains(?array $geometry, float $lng, float $lat): bool
    {
        foreach (self::polygons($geometry) as $polygon) {
            if (! self::inRing($polygon[0] ?? [], $lng, $lat)) {
                continue;
            }

            foreach (array_slice($polygon, 1) as $hole) {
                if (self::inRing($hole, $lng, $lat)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private static function polygons(?array $geometry): array
    {
        if (! $geometry || ! isset($geometry['type'], $geometry['coordinates'])) {
            return [];
        }

        return match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
    }

    private static function signedArea(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
            $sum += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
        }

        return $sum / 2;
    }

    private static function inRing(array $ring, float $lng, float $lat): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1, $n = count($ring); $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
