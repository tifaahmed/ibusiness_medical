<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The dashboard's bell: things an admin should hear about (a new order first). ADD ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('type', 40)->index();
                $table->json('title');
                $table->json('body')->nullable();
                $table->string('url', 500)->nullable();
                $table->json('data')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('admin_notification_reads')) {
            Schema::create('admin_notification_reads', function (Blueprint $table) {
                $table->foreignId('admin_notification_id')->constrained('admin_notifications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('read_at')->nullable();
                $table->primary(['admin_notification_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop tables that hold data.
    }
};
