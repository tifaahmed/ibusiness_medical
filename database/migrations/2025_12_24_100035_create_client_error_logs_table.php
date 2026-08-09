<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->string('route')->nullable();
            $table->boolean('fatal')->default(false);
            $table->text('message')->nullable();
            $table->longText('stack')->nullable();
            $table->json('extra')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_error_logs');
    }
};
