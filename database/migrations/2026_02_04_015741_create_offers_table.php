<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();

            // Polymorphic relationship - can belong to any model (Facility, FacilityBranch, etc.)
            $table->morphs('offerable'); // Creates offerable_id and offerable_type

            // Offer details
            $table->json('title'); // Multi-language support
            $table->json('short_description')->nullable(); // Multi-language support
            $table->json('full_description')->nullable(); // Multi-language support

            $table->string('phone')->nullable();

            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('old_price', 8, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
