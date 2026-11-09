<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit trail of the master login code (who turned it on/off/changed it, when, until when). Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('otp_master_code_logs')) {
            return;
        }

        Schema::create('otp_master_code_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->index();
            $table->string('action', 30); // enabled | disabled | changed | used
            $table->timestamp('expires_at')->nullable();
            $table->string('phone_masked', 40)->nullable(); // for `used`: whose login it opened
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Audit data is never dropped.
    }
};
