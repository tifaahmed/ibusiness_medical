<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A visit counter on each facility and store: +1 whenever its page is opened in the app (shown on the page, used by the app's "most visited" lists). */
    public function up(): void
    {
        foreach (['facilities', 'stores'] as $table) {
            if (! Schema::hasColumn($table, 'visits_count')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedInteger('visits_count')->default(0)->index();
                });
            }
        }

        // Facilities already have page visits recorded: start from them.
        if (Schema::hasTable('page_visits')) {
            DB::statement('update facilities set visits_count = (select count(*) from page_visits where page_visits.facility_id = facilities.id) where visits_count = 0');
        }
    }

    public function down(): void
    {
        foreach (['facilities', 'stores'] as $table) {
            if (Schema::hasColumn($table, 'visits_count')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('visits_count'));
            }
        }
    }
};
