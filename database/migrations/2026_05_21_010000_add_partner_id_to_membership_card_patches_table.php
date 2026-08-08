<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('created_by')
                ->constrained('partners')->nullOnDelete();
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
