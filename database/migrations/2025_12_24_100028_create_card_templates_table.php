<?php

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            // Translatable (spatie/laravel-translatable), same as companies.name.
            $table->json('name');
            $table->string('slug')->unique();
            // Whether this template's layout reserves a partner-logo slot.
            $table->enum('status', CardTemplateStatusEnum::values())
                ->default(CardTemplateStatusEnum::WITH_PARTNER->value);

            // Public-relative paths: the blank artwork every card of this type
            // is printed on, and a rendered example of the default layout.
            $table->string('card_empty')->nullable();
            $table->string('sample_card')->nullable();

            // `layout` positions every field: x/y/width/height as fractions of
            // the card (0..1) so it survives any render size, plus font/colour/
            // direction for text fields. `sample_data` holds the values — the
            // contact lines that are fixed for the whole template, and
            // placeholders for the per-member fields so the card can be
            // previewed before a member exists. See CardTemplateLayoutDefaults.
            $table->json('layout')->nullable();
            $table->json('sample_data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_templates');
    }
};
