<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Numbers inside a batch's range that the batch skipped because another card already held them. Add-only. */
    public function up(): void
    {
        if (! Schema::hasColumn('membership_card_patches', 'skipped_numbers')) {
            Schema::table('membership_card_patches', function (Blueprint $table) {
                $table->json('skipped_numbers')->nullable()->after('membership_ids');
            });
        }
        // A random batch: the numbers it reserves, listed one by one (no start..end run). Null = a sequential batch.
        if (! Schema::hasColumn('membership_card_patches', 'number_list')) {
            Schema::table('membership_card_patches', function (Blueprint $table) {
                $table->json('number_list')->nullable()->after('skipped_numbers');
            });
        }
    }

    public function down(): void
    {
        // Never dropped: the column may hold the record of which numbers a printed batch skipped.
    }
};
