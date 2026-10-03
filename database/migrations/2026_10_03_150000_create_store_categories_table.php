<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('stores', function (Blueprint $table) {
            // Deleting a category leaves its stores uncategorised rather than deleting them.
            $table->foreignId('store_category_id')->nullable()->after('slug')
                ->constrained('store_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_category_id');
        });
        Schema::dropIfExists('store_categories');
    }
};
