<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The seconds a player took to answer (0-20), kept with the answer: the points of a right answer depend on it. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loyalty_transactions') && ! Schema::hasColumn('loyalty_transactions', 'answer_seconds')) {
            Schema::table('loyalty_transactions', function (Blueprint $table): void {
                $table->decimal('answer_seconds', 5, 2)->nullable()->after('timed_out');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loyalty_transactions', 'answer_seconds')) {
            Schema::table('loyalty_transactions', function (Blueprint $table): void {
                $table->dropColumn('answer_seconds');
            });
        }
    }
};
