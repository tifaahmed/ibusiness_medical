<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('iframe_location')->nullable()->after('city_id');
            $table->decimal('lat', 10, 7)->nullable()->after('iframe_location');
            $table->decimal('long', 10, 7)->nullable()->after('lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['iframe_location', 'lat', 'long']);
        });
    }
};
