<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnmarkedCitySeeder extends Seeder
{
    /**
     * Gives each governorate an "Unmarked City": the ground its city borders
     * leave empty on the map, so a place with no city of its own still has one
     * to belong to.
     *
     * database/data/egypt-unmarked-boundaries.json is keyed by governorate slug;
     * each border is that governorate's outline minus the union of its cities'
     * borders in egypt-city-boundaries.json, with pieces under 2 km² dropped
     * (they are seams between two simplified outlines, not places). A
     * governorate whose cities already cover it (Cairo) has no entry and gets no
     * city. Regenerate the file if the city borders change.
     *
     * Safe to re-run: the city is found by its English name before it is made.
     */
    public function run(): void
    {
        $path = database_path('data/egypt-unmarked-boundaries.json');

        try {
            $borders = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('UnmarkedCitySeeder: could not read the borders file', ['path' => $path, 'exception' => $e]);
            throw $e;
        }

        $stored = 0;
        foreach ($borders as $slug => $boundary) {
            $governorate = Governorate::where('slug', $slug)->first();
            if (! $governorate) {
                Log::warning('UnmarkedCitySeeder: governorate not on this site, skipped', ['slug' => $slug]);

                continue;
            }

            $city = $governorate->cities()->where('name->en', City::UNMARKED_NAME['en'])->first()
                ?? $governorate->cities()->create(['name' => City::UNMARKED_NAME]);

            // Query builder, not the model: `boundary` stays out of $fillable.
            DB::table('cities')->where('id', $city->id)->update(['boundary' => json_encode($boundary)]);
            $stored++;
        }

        $this->command?->info("{$stored} unmarked cities stored.");
    }
}
