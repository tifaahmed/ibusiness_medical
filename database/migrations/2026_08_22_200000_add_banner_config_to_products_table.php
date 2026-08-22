<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Same shape as facilities.banner_config: the corner ribbon's copy,
            // colours and lifetime, kept as one JSON blob.
            $table->json('banner_config')->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('banner_config');
        });
    }
};
