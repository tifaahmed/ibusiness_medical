<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->onDelete('cascade');
            $table->enum('type', ['home', 'work', 'other'])->default('home');
            $table->text('address')->nullable();
            $table->string('street')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('building_number', 50)->nullable();
            $table->string('apartment_number', 50)->nullable();
            $table->string('floor_number', 50)->nullable();
            $table->string('special_mark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('membership_id');
            $table->index('type');
            $table->index('governorate_id');
            $table->index('city_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
