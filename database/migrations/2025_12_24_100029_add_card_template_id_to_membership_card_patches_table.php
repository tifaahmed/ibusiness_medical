<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            // The design the batch was cut from. Nullable so batches created
            // before card templates existed still render (the renderer falls
            // back to its built-in layout), and nullOnDelete so removing a
            // template never takes a batch with it.
            $table->foreignId('card_template_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('card_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membership_card_patches', function (Blueprint $table) {
            $table->dropForeign(['card_template_id']);
            $table->dropColumn('card_template_id');
        });
    }
};
