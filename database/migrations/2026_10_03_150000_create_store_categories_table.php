<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a re-run after a partial or hand-applied change is harmless.
        if (! Schema::hasTable('store_categories')) {
            Schema::create('store_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        // 2026_10_03_190000 moves this to a pivot and drops it, so a database that
        // already ran that far must not get the column back.
        if (! Schema::hasColumn('stores', 'store_category_id') && ! Schema::hasTable('store_category_store')) {
            Schema::table('stores', function (Blueprint $table) {
                // Deleting a category leaves its stores uncategorised rather than deleting them.
                $table->foreignId('store_category_id')->nullable()->after('slug')
                    ->constrained('store_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_category_id');
        });
        Schema::dropIfExists('store_categories');
    }
};
