<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->string('prefix', 32)->nullable()->after('batch_name');
        });
    }

    public function down(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->dropColumn('prefix');
        });
    }
};
