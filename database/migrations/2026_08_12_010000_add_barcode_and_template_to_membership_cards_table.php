<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single-card generator draws from a card template now, so a saved layout
 * has two more things to remember: where the barcode sits (the template's
 * design has one; the old hardcoded artwork did not) and which template the
 * card was cut from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->decimal('barcode_x', 8, 2)->nullable()->after('qr_scale');
            $table->decimal('barcode_y', 8, 2)->nullable()->after('barcode_x');
            $table->decimal('barcode_scale', 6, 3)->nullable()->after('barcode_y');
            $table->foreignId('card_template_id')->nullable()->after('partner_id')
                ->constrained('card_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_template_id');
            $table->dropColumn(['barcode_x', 'barcode_y', 'barcode_scale']);
        });
    }
};
