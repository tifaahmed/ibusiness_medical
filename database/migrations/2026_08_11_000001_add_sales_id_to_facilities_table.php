<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('facility_type_id')->constrained('sales')->nullOnDelete();

            $table->index('sales_id');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->dropIndex(['sales_id']);
            $table->dropColumn('sales_id');
        });
    }
};
