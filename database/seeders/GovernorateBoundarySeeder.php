<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernorateBoundarySeeder extends Seeder
{
    /**
     * Stores each governorate's border, matched by slug, from
     * database/data/egypt-governorate-boundaries.json.
     *
     * Source: geoBoundaries gbOpen EGY ADM1 (simplified, from OpenStreetMap,
     * ODbL), coordinates rounded to 4 decimals (~11 m). Governorates missing from the file (e.g. Helwan) are
     * left without a border. Safe to re-run.
     */
    public function run(): void
    {
        $path = database_path('data/egypt-governorate-boundaries.json');
        $boundaries = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $updated = 0;
        foreach ($boundaries as $slug => $geometry) {
            // Query builder, not the model: no slug/translation events, and
            // `boundary` stays out of $fillable on purpose.
            $updated += DB::table('governorates')
                ->where('slug', $slug)
                ->update(['boundary' => json_encode($geometry)]);
        }

        $this->command?->info("{$updated} governorate boundaries stored.");
    }
}
