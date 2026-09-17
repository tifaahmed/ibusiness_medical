<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('short_description')->nullable();
            $table->string('slug')->unique();
            $table->string('youtube_link')->nullable();
            $table->decimal('offer_percent_from', 5, 2)->nullable();
            $table->decimal('offer_percent_to', 5, 2)->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
