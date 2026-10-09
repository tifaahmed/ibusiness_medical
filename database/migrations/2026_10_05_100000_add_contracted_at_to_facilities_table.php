<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->date('contracted_at')->nullable()->after('discount_percent');
        });

        // Existing facilities: the best record of when they signed is when they were added.
        DB::table('facilities')->whereNull('contracted_at')->update(['contracted_at' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('contracted_at');
        });
    }
};
