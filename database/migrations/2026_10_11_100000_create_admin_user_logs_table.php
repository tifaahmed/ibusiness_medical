<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_user_logs')) {
            return;
        }

        Schema::create('admin_user_logs', function (Blueprint $table) {
            $table->id();
            // No foreign keys: the trail must outlive both the account it is
            // about and the admin who acted, so name/email are snapshotted.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name')->nullable();
            $table->string('action', 32)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_logs');
    }
};
