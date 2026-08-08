<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_cards')) {
            return;
        }

        Schema::create('membership_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreign('partner_id', 'mc_partner_id_fk')->references('id')->on('partners')->onDelete('set null');

            $table->decimal('partner_x', 8, 2)->nullable();
            $table->decimal('partner_y', 8, 2)->nullable();
            $table->decimal('partner_scale', 5, 3)->nullable();

            $table->decimal('photo_x', 8, 2)->nullable();
            $table->decimal('photo_y', 8, 2)->nullable();
            $table->decimal('photo_scale', 5, 3)->nullable();

            $table->decimal('name_x', 8, 2)->nullable();
            $table->decimal('name_y', 8, 2)->nullable();
            $table->decimal('name_scale', 5, 3)->nullable();
            $table->string('name_color', 7)->nullable();

            $table->decimal('fields_x', 8, 2)->nullable();
            $table->decimal('fields_y', 8, 2)->nullable();
            $table->decimal('fields_scale', 5, 3)->nullable();
            $table->string('fields_color', 7)->nullable();

            $table->decimal('qr_x', 8, 2)->nullable();
            $table->decimal('qr_y', 8, 2)->nullable();
            $table->decimal('qr_scale', 5, 3)->nullable();

            $table->string('generated_image_path')->nullable();
            $table->string('mode', 16)->default('full');
            $table->timestamps();

            $table->unique(['membership_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_cards');
    }
};