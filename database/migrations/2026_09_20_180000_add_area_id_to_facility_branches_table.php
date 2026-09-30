<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_branches', function (Blueprint $table) {
            // Optional: most branches are known to the city and no closer. Deleting
            // an area only forgets the link — the branch stays where it was.
            $table->foreignId('area_id')->nullable()->after('city_id')->constrained('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facility_branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
