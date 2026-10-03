<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // true = ships to every governorate (the pivot is ignored);
            // false = only the governorates listed in store_governorate.
            $table->boolean('ships_everywhere')->default(true)->after('coupons');
        });

        Schema::create('store_governorate', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_id', 'governorate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_governorate');
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('ships_everywhere');
        });
    }
};
