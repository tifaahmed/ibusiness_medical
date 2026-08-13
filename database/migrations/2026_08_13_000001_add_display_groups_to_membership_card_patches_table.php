<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a batch's number is broken up for reading on the printed card — '3-3-3'
 * prints 123456789 as 123-456-789.
 *
 * It sits beside `display_prefix` because it is the same kind of thing: card
 * decoration, never part of the membership number. The number itself stays
 * whole in `memberships.membership_number`, which is what the barcode encodes
 * and what every lookup resolves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->string('display_groups', 32)->nullable()->after('display_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->dropColumn('display_groups');
        });
    }
};
