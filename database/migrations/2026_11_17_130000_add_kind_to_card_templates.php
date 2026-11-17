<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `physical` (the printed card — every existing template) or `soft` (the card the member sees on screen, which also
 * carries the member's full name and membership expiry date). ADD-ONLY: one defaulted column, no row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('card_templates', 'kind')) {
            Schema::table('card_templates', function (Blueprint $table) {
                $table->string('kind', 10)->default('physical')->after('status');
                $table->index('kind');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop a column that may hold data.
    }
};
