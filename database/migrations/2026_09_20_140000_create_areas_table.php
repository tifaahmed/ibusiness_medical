<?php

use Database\Seeders\AreaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->json('name');
            // CAPMAS / OCHA COD-AB place code (e.g. EG120918). The natural key
            // the seeder upserts on, so a re-run never duplicates an area.
            $table->string('pcode')->unique();
            $table->string('slug')->unique();
            // GeoJSON geometry (Polygon / MultiPolygon) of the area's border.
            $table->json('boundary')->nullable();
            $table->timestamps();

            $table->index('governorate_id');
            $table->index('city_id');
        });

        // Fill the areas in right away so a deploy needs no extra seeding step.
        (new AreaSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
