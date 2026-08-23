<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give an order the same structured delivery address a member keeps:
     * type (home/work/other), street, government & city, building, apartment,
     * floor and the special mark that finds the door.
     *
     * Governorate and city are plain text on purpose — an order is an archive
     * of what the buyer said at purchase time (the same reason order_products
     * copies names instead of joining), so it must not move when a lookup
     * table is later renamed or a row disappears.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('customer_address_type', ['home', 'work', 'other'])
                ->nullable()
                ->after('customer_address');
            $table->string('customer_street')->nullable()->after('customer_address_type');
            $table->string('customer_governorate')->nullable()->after('customer_street');
            $table->string('customer_city')->nullable()->after('customer_governorate');
            $table->string('customer_building_number', 50)->nullable()->after('customer_city');
            $table->string('customer_apartment_number', 50)->nullable()->after('customer_building_number');
            $table->string('customer_floor_number', 50)->nullable()->after('customer_apartment_number');
            $table->string('customer_special_mark')->nullable()->after('customer_floor_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_address_type',
                'customer_street',
                'customer_governorate',
                'customer_city',
                'customer_building_number',
                'customer_apartment_number',
                'customer_floor_number',
                'customer_special_mark',
            ]);
        });
    }
};
