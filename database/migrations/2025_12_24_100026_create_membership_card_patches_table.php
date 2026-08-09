<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_card_patches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_name')->nullable();
            $table->string('prefix', 32)->nullable();
            $table->string('display_prefix', 32)->nullable();
            $table->json('layout_overrides')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('start_number');
            // Plain JSON list of membership IDs the batch created. No FK — the
            // batch row is intentionally decoupled so deleting it leaves the
            // generated memberships untouched.
            $table->json('membership_ids');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_by');
            $table->index('partner_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_card_patches');
    }
};
