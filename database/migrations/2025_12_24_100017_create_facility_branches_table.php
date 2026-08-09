<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('cascade');
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('name')->nullable();
            $table->string('slug')->unique();
            $table->json('address')->nullable();
            $table->json('phone')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('facility_id');
            $table->index('governorate_id');
            $table->index('city_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_branches');
    }
};
