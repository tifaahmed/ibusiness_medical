<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_tag', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_id', 'tag_id']);
        });

        // "services" was replaced by "stores" as a tag target.
        DB::table('tags')->whereNotNull('applies_to')->get(['id', 'applies_to'])->each(function ($tag) {
            $targets = collect(json_decode($tag->applies_to, true) ?: [])
                ->map(fn ($t) => $t === 'services' ? 'stores' : $t)->unique()->values()->all();
            DB::table('tags')->where('id', $tag->id)->update(['applies_to' => json_encode($targets)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_tag');
    }
};
