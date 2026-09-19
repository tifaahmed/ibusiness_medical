<?php

use Database\Seeders\GovernorateBoundarySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            // GeoJSON geometry (Polygon / MultiPolygon) of the governorate's
            // border, drawn on the branch map when it is filtered by governorate.
            $table->json('boundary')->nullable()->after('slug');
        });

        // Fill the borders in right away so a deploy needs no extra seeding step.
        (new GovernorateBoundarySeeder)->run();
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropColumn('boundary');
        });
    }
};
