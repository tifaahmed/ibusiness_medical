<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            // Root-relative paths ("storage/card-templates/…"), like card_empty.
            $table->string('back_image')->nullable()->after('sample_card');
            $table->string('back_logo')->nullable()->after('back_image');
            // Which pieces show and what they say. Null = the template has no
            // custom back and the shipped artwork is used, as before.
            $table->json('back_settings')->nullable()->after('back_logo');
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->dropColumn(['back_image', 'back_logo', 'back_settings']);
        });
    }
};
