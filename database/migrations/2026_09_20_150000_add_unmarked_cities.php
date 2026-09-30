<?php

use App\Models\City;
use Database\Seeders\UnmarkedCitySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fill them in right away so a deploy needs no extra seeding step.
        (new UnmarkedCitySeeder)->run();
    }

    public function down(): void
    {
        DB::table('cities')->where('name->en', City::UNMARKED_NAME['en'])->delete();
    }
};
