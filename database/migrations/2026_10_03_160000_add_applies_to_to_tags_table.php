<?php

use App\Enums\Tag\TagTargetEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // ["facilities","products","services"] — which kinds of record may carry the tag.
            $table->json('applies_to')->nullable()->after('color');
        });

        // Existing tags keep working everywhere they did before.
        DB::table('tags')->update(['applies_to' => json_encode(TagTargetEnum::values())]);
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
