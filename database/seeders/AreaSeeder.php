<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AreaSeeder extends Seeder
{
    /**
     * Stores the areas (admin level 3) from database/data/egypt-area-boundaries.json.
     *
     * Each entry is {pcode, ar, city, boundary}; `city` is keyed
     * "<governorate slug>/<English name, or Arabic when the city has none>",
     * the same key CityBoundarySeeder uses, so it survives different row ids
     * between environments. An entry whose city this site lacks is skipped and
     * counted, never guessed. Upserts on `pcode`: safe to re-run.
     *
     * Source: OCHA COD-AB Egypt (CAPMAS 2017), CC BY-IGO, simplified,
     * coordinates rounded to 4 decimals (~11 m). Each unit is filed under the
     * SMALLEST city border that holds its centre, or under its governorate's
     * "Unmarked City" when no border does (see refile()).
     */
    public function run(): void
    {
        $areas = $this->areas();
        $cities = $this->cityIndex();

        $now = now();
        $rows = [];
        $skipped = 0;
        foreach ($areas as $area) {
            if (! isset($cities[$area['city']])) {
                $skipped++;

                continue;
            }

            [$cityId, $governorateId] = $cities[$area['city']];
            $rows[] = $this->row($area, $cityId, $governorateId, $now);
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('areas')->upsert(
                $chunk,
                ['pcode'],
                ['governorate_id', 'city_id', 'name', 'slug', 'boundary', 'updated_at'],
            );
        }

        if ($skipped > 0) {
            Log::warning('AreaSeeder: areas skipped because their city is not on this site', ['skipped' => $skipped]);
        }

        $this->command?->info(count($rows).' areas stored'.($skipped ? ", {$skipped} skipped (city missing)" : '').'.');
    }

    /**
     * Re-files the imported areas under the city the file now names, WITHOUT
     * touching anything an admin may have edited since (name, border): only
     * `city_id` / `governorate_id` move, and only where they differ. An area
     * the table lacks (its city did not exist on the first run — the
     * "Unmarked City" of a fresh install) is inserted. Areas added by hand are
     * not in the file and are never touched. Safe to re-run.
     *
     * The file files each area under the SMALLEST city border that holds its
     * centre, or under its governorate's Unmarked City when no border does —
     * so an area sits in a city it actually lies in, and an outer city border
     * (e.g. "Alexandria") no longer swallows the areas of the districts inside it.
     *
     * @return array{moved: int, inserted: int, skipped: int}
     */
    public function refile(): array
    {
        $cities = $this->cityIndex();
        $existing = DB::table('areas')->pluck('city_id', 'pcode');
        $now = now();

        $moved = $skipped = 0;
        $missing = [];
        foreach ($this->areas() as $area) {
            if (! isset($cities[$area['city']])) {
                $skipped++;

                continue;
            }

            [$cityId, $governorateId] = $cities[$area['city']];

            if (! isset($existing[$area['pcode']])) {
                $missing[] = $this->row($area, $cityId, $governorateId, $now);

                continue;
            }

            if ((int) $existing[$area['pcode']] !== $cityId) {
                $moved += DB::table('areas')->where('pcode', $area['pcode'])
                    ->update(['city_id' => $cityId, 'governorate_id' => $governorateId, 'updated_at' => $now]);
            }
        }

        foreach (array_chunk($missing, 200) as $chunk) {
            DB::table('areas')->insertOrIgnore($chunk);
        }

        if ($skipped > 0) {
            Log::warning('AreaSeeder::refile: areas skipped because their city is not on this site', ['skipped' => $skipped]);
        }
        Log::info('AreaSeeder::refile done', ['moved' => $moved, 'inserted' => count($missing), 'skipped' => $skipped]);
        $this->command?->info("{$moved} areas re-filed, ".count($missing).' inserted'.($skipped ? ", {$skipped} skipped (city missing)" : '').'.');

        return ['moved' => $moved, 'inserted' => count($missing), 'skipped' => $skipped];
    }

    private function areas(): array
    {
        $path = database_path('data/egypt-area-boundaries.json');

        try {
            return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('AreaSeeder: could not read the areas file', ['path' => $path, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * "<governorate slug>/<English name, or Arabic when none>" => [city id, governorate id].
     */
    private function cityIndex(): array
    {
        $cities = [];
        foreach (City::with('governorate:id,slug')->get() as $city) {
            $name = $city->getTranslation('name', 'en', false) ?: $city->getTranslation('name', 'ar', false);
            $cities[($city->governorate?->slug).'/'.$name] = [$city->id, $city->governorate_id];
        }

        return $cities;
    }

    private function row(array $area, int $cityId, int $governorateId, $now): array
    {
        return [
            'governorate_id' => $governorateId,
            'city_id' => $cityId,
            'name' => json_encode(['ar' => $area['ar']], JSON_UNESCAPED_UNICODE),
            'pcode' => $area['pcode'],
            'slug' => strtolower($area['pcode']),
            'boundary' => json_encode($area['boundary']),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
