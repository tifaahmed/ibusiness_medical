<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_offer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_offer_id')->constrained('partner_offers')->cascadeOnDelete();
            $table->string('phone_number');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offer_requests');
    }
};
