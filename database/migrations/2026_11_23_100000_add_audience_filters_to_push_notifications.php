<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A push can go to a GROUP picked by filters. Only ADDS one nullable JSON column. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('push_notifications', 'audience_filters')) {
            Schema::table('push_notifications', fn (Blueprint $t) => $t->json('audience_filters')->nullable());
        }
    }

    public function down(): void {}
};
