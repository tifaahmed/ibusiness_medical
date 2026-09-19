<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CityBoundarySeeder extends Seeder
{
    /**
     * Stores each city's border from database/data/egypt-city-boundaries.json.
     *
     * Entries are keyed "<governorate slug>/<English name, or Arabic when the
     * city has none>" so they survive different row ids between environments.
     * Cities missing from the file keep no border. Safe to re-run.
     *
     * Source: OpenStreetMap (Nominatim) outlines where OSM has one; otherwise
     * the geoBoundaries ADM2 district containing the city (ODbL). Both are
     * simplified, coordinates rounded to 4 decimals (~11 m).
     */
    public function run(): void
    {
        $path = database_path('data/egypt-city-boundaries.json');
        $boundaries = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $updated = 0;
        foreach (City::with('governorate:id,slug')->get() as $city) {
            $name = $city->getTranslation('name', 'en', false) ?: $city->getTranslation('name', 'ar', false);
            $key = ($city->governorate?->slug).'/'.$name;

            if (! isset($boundaries[$key])) {
                continue;
            }

            // Query builder, not the model: no slug/translation events, and
            // `boundary` stays out of $fillable on purpose.
            $updated += DB::table('cities')
                ->where('id', $city->id)
                ->update(['boundary' => json_encode($boundaries[$key])]);
        }

        $this->command?->info("{$updated} city boundaries stored.");
    }
}
