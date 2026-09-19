<?php

use Database\Seeders\CityBoundarySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // GeoJSON geometry (Polygon / MultiPolygon) of the city's border,
            // drawn on the branch map when it is filtered by city.
            $table->json('boundary')->nullable();
        });

        // Fill the borders in right away so a deploy needs no extra seeding step.
        (new CityBoundarySeeder)->run();
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('boundary');
        });
    }
};
