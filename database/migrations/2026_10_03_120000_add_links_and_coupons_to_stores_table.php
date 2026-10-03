<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // All optional. websites: ["https://…"]; social_links: [{platform, url}];
            // coupons: [{code, title: {ar, en}, expires_at}] — replaced wholesale on save.
            $table->json('websites')->nullable()->after('youtube_link');
            $table->json('social_links')->nullable()->after('websites');
            $table->json('coupons')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['websites', 'social_links', 'coupons']);
        });
    }
};
