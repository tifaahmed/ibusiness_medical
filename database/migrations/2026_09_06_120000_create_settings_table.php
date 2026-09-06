<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // The programmatic key a row is read by — Setting::get('deilar_phone').
            // Unique because a duplicate key silently shadows one of the two rows.
            $table->string('slug')->unique();
            // The human label shown in the admin list, translated like every
            // other name in this schema.
            $table->json('name');
            // Every value lands here as text; `value_type` says how to read it
            // back. Nullable so a row can exist before anyone fills it in.
            $table->text('value')->nullable();
            $table->string('value_type')->default('string');
            $table->timestamps();

            $table->index('value_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
