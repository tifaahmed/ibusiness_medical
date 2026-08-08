<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('card_x', 8, 2)->nullable()->after('title');
            $table->decimal('card_y', 8, 2)->nullable()->after('card_x');
            $table->decimal('card_scale', 5, 3)->nullable()->after('card_y');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['card_x', 'card_y', 'card_scale']);
        });
    }
};
