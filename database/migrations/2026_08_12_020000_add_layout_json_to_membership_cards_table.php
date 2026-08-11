<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card generator now positions every template field on its own, the way the
 * template editor does, rather than nudging four grouped elements. That is a
 * whole layout per card, so it needs a layout column rather than the fixed
 * {element}_x/y/scale ones — which stay for the layouts already saved in them.
 *
 * `field_values` holds what the admin typed into each field for this one card
 * (slogan, contact rows, QR payload…), overriding the template's sample_data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('card_template_id');
            $table->json('field_values')->nullable()->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->dropColumn(['layout', 'field_values']);
        });
    }
};
