<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_branches', function (Blueprint $table) {
            $table->foreignId('governorate_id')->nullable()->after('facility_id')->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('governorate_id')->constrained('cities')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('city_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->index('governorate_id');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('facility_branches', function (Blueprint $table) {
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['governorate_id', 'city_id', 'latitude', 'longitude']);
        });
    }
};
