<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** The "specific members" audience holds a LIST of phones (comma separated), not one. Only widens the column. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('push_notifications', 'audience_phone')) {
            Schema::table('push_notifications', fn ($t) => $t->text('audience_phone')->nullable()->change());
        }
    }

    public function down(): void {}
};
