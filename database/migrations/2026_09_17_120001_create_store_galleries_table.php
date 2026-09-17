<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('media_path');
            // 'image' or 'video' — the show page renders each item differently
            // (an <img> versus a <video> with controls) and validation caps a
            // video's own upload separately from an image's.
            $table->string('type')->default('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_galleries');
    }
};
