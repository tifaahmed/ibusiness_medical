<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('slug')->unique();
            $table->foreignId('facility_type_id')->constrained('facility_types')->onDelete('cascade');
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->onDelete('cascade');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('slug');
            $table->index('facility_type_id');
            $table->index('governorate_id');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
