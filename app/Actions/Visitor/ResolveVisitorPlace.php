<?php

namespace App\Actions\Visitor;

use App\Models\City;
use App\Models\Governorate;
use App\Support\GeoJson;
use Illuminate\Support\Facades\Cache;

/**
 * GPS point → governorate / city, using the borders stored on those tables.
 * Cached per ~1 km cell because the same cell is asked for on every page view.
 */
class ResolveVisitorPlace
{
    /** @return array{governorate_id: ?int, city_id: ?int} */
    public function handle(float $lat, float $lng): array
    {
        return Cache::remember(
            sprintf('visitor-place:%.2f,%.2f', $lat, $lng),
            now()->addDay(),
            fn () => $this->lookup($lat, $lng),
        );
    }

    /** @return array{governorate_id: ?int, city_id: ?int} */
    private function lookup(float $lat, float $lng): array
    {
        $governorateId = null;
        foreach (Governorate::query()->whereNotNull('boundary')->get(['id', 'boundary']) as $governorate) {
            if (GeoJson::contains($governorate->boundary, $lng, $lat)) {
                $governorateId = $governorate->id;
                break;
            }
        }

        $cityId = null;
        $cities = City::query()->whereNotNull('boundary')
            ->when($governorateId, fn ($q) => $q->where('governorate_id', $governorateId))
            ->get(['id', 'boundary']);
        foreach ($cities as $city) {
            if (GeoJson::contains($city->boundary, $lng, $lat)) {
                $cityId = $city->id;
                break;
            }
        }

        return ['governorate_id' => $governorateId, 'city_id' => $cityId];
    }
}
