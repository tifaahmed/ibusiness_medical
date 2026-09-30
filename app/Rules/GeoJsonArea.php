<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GeoJSON Polygon / MultiPolygon that could be a border inside Egypt: closed
 * rings of at least 4 positions, every position [lng, lat] within Egypt's
 * bounding box (with a little slack). It is a sanity net for the border editor,
 * not a topology check — a dragged vertex can still make a ring cross itself.
 */
class GeoJsonArea implements ValidationRule
{
    private const LNG = [24.0, 37.0];

    private const LAT = [21.0, 32.0];

    private const MAX_POSITIONS = 60000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! in_array($value['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || ! is_array($value['coordinates'] ?? null)) {
            $fail('The :attribute must be a GeoJSON Polygon or MultiPolygon.');

            return;
        }

        $polygons = $value['type'] === 'Polygon' ? [$value['coordinates']] : $value['coordinates'];
        $positions = 0;

        if ($polygons === []) {
            $fail('The :attribute has no polygon.');

            return;
        }

        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                $fail('The :attribute has an empty polygon.');

                return;
            }

            foreach ($polygon as $ring) {
                if (! is_array($ring) || count($ring) < 4 || $ring[0] !== end($ring)) {
                    $fail('Every ring of the :attribute needs at least 4 positions and must close on its first one.');

                    return;
                }

                foreach ($ring as $position) {
                    $positions++;

                    if (! is_array($position) || count($position) < 2 || ! is_numeric($position[0]) || ! is_numeric($position[1])
                        || $position[0] < self::LNG[0] || $position[0] > self::LNG[1]
                        || $position[1] < self::LAT[0] || $position[1] > self::LAT[1]) {
                        $fail('The :attribute has a position outside Egypt.');

                        return;
                    }
                }
            }
        }

        if ($positions > self::MAX_POSITIONS) {
            $fail('The :attribute is too detailed.');
        }
    }
}
