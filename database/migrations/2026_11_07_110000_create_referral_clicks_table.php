<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tap on a shared invitation link (`/r/<card number>`), recorded by the website with a hash of the visitor's IP. When the app opens
 * for the first time it asks which code (if any) was tapped from the same IP in the last day, so a code survives the trip through the
 * store. Only a hash is kept, only briefly useful (24 h), and a click is matched at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referral_clicks')) {
            return;
        }

        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('ip_hash', 64)->index();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
