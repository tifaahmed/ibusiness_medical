<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a re-run after a partial or hand-applied change is harmless.
        if (! Schema::hasTable('store_category_store')) {
            Schema::create('store_category_store', function (Blueprint $table) {
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('store_category_id')->constrained()->cascadeOnDelete();
                $table->primary(['store_id', 'store_category_id']);
            });
        }

        // Nothing to carry over or drop once the single-category column is gone.
        if (! Schema::hasColumn('stores', 'store_category_id')) {
            return;
        }

        // A store used to hold exactly one category; carry it over (skipping pairs already there).
        DB::table('stores')->whereNotNull('store_category_id')->get(['id', 'store_category_id'])
            ->each(fn ($s) => DB::table('store_category_store')->insertOrIgnore([
                'store_id' => $s->id,
                'store_category_id' => $s->store_category_id,
            ]));

        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('store_category_id')->nullable()->after('slug')
                ->constrained('store_categories')->nullOnDelete();
        });

        // Only one survives the way back: the lowest-id category.
        DB::table('store_category_store')->orderBy('store_category_id')->get()->unique('store_id')
            ->each(fn ($r) => DB::table('stores')->where('id', $r->store_id)->update(['store_category_id' => $r->store_category_id]));

        Schema::dropIfExists('store_category_store');
    }
};
