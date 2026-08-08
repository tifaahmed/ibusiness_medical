<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->string('display_prefix', 32)->nullable()->after('prefix');
            $table->json('layout_overrides')->nullable()->after('display_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->dropColumn(['display_prefix', 'layout_overrides']);
        });
    }
};
