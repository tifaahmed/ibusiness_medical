<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('created_by');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_types');
    }
};
