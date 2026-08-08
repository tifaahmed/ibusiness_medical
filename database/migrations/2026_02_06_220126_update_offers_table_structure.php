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
        Schema::table('offers', function (Blueprint $table) {
            // Add slug column if it doesn't exist
            if (!Schema::hasColumn('offers', 'slug')) {
                $table->string('slug')->unique()->after('id');
            }

            // Rename description to short_description
            if (Schema::hasColumn('offers', 'description') && !Schema::hasColumn('offers', 'short_description')) {
                $table->renameColumn('description', 'short_description');
            }

            // Add full_description if it doesn't exist
            if (!Schema::hasColumn('offers', 'full_description')) {
                $table->json('full_description')->nullable()->after('short_description');
            }

            // Add phone column if it doesn't exist
            if (!Schema::hasColumn('offers', 'phone')) {
                $table->string('phone', 50)->nullable()->after('full_description');
            }

            // Add price and old_price columns if they don't exist
            if (!Schema::hasColumn('offers', 'price')) {
                $table->decimal('price', 8, 2)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('offers', 'old_price')) {
                $table->decimal('old_price', 8, 2)->nullable()->after('price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Remove added columns
            if (Schema::hasColumn('offers', 'old_price')) {
                $table->dropColumn('old_price');
            }
            if (Schema::hasColumn('offers', 'price')) {
                $table->dropColumn('price');
            }
            if (Schema::hasColumn('offers', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('offers', 'full_description')) {
                $table->dropColumn('full_description');
            }

            // Rename short_description back to description
            if (Schema::hasColumn('offers', 'short_description')) {
                $table->renameColumn('short_description', 'description');
            }

            // Remove slug
            if (Schema::hasColumn('offers', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
