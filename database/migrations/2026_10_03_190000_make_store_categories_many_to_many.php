<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_category_store', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_id', 'store_category_id']);
        });

        // A store used to hold exactly one category; carry it over.
        DB::table('stores')->whereNotNull('store_category_id')->get(['id', 'store_category_id'])
            ->each(fn ($s) => DB::table('store_category_store')->insert([
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
