<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stores', 'contracted_at')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            // Nullable: stores that existed before this column have no known date.
            $table->date('contracted_at')->nullable()->after('sales_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'contracted_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('contracted_at');
            });
        }
    }
};
