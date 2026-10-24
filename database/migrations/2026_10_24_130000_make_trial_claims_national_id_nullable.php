<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The free trial no longer asks for a national ID: one trial per PHONE is the only limit. Old claims keep their ID;
 * new ones leave it empty (a unique index allows any number of NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_claims', function (Blueprint $table) {
            $table->char('national_id', 14)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows without an ID cannot go back to NOT NULL; left as is on purpose.
    }
};
