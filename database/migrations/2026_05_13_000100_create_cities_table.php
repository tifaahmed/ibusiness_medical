<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('slug');
            $table->index('governorate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
