<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Website or mobile app (`ContactChannelEnum`). Null = paid before this was recorded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->string('channel', 16)->nullable()->after('method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('membership_subscriptions', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
