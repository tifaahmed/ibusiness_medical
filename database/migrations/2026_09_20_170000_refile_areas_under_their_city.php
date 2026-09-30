<?php

use Database\Seeders\AreaSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Every imported area now sits under the smallest city border holding its
 * centre, or its governorate's Unmarked City when no border does. Before, an
 * area went to whichever overlapping city matched first — so outer borders
 * swallowed their districts' areas — and 157 had no city border at all.
 *
 * Only `city_id` / `governorate_id` move; names and borders an admin edited
 * are left alone. Runs after 2026_09_20_150000, which creates the Unmarked
 * Cities the file now names.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AreaSeeder)->refile();
    }

    public function down(): void
    {
        // The previous filing is not recoverable from the file, and the new one
        // is the correct one; nothing to undo.
    }
};
