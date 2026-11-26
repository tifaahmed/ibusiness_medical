<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scheduled push notifications (once / weekly / monthly). Only ADDS: one table and one nullable column. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_schedules')) {
            Schema::create('push_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('title', 150);
                $table->text('body');
                $table->string('url', 500)->nullable();
                $table->string('audience', 16)->default('all');
                $table->text('audience_phone')->nullable();
                $table->json('audience_filters')->nullable();
                $table->string('frequency', 16); // once | weekly | monthly
                $table->date('run_date')->nullable(); // once
                $table->unsignedTinyInteger('weekday')->nullable(); // weekly: 0 Sunday … 6 Saturday
                $table->unsignedTinyInteger('month_day')->nullable(); // monthly: 1–31
                $table->string('send_time', 5); // H:i in the app timezone (Africa/Cairo)
                $table->boolean('is_active')->default(true);
                $table->dateTime('next_run_at')->nullable();
                $table->dateTime('last_run_at')->nullable();
                $table->unsignedInteger('runs_count')->default(0);
                $table->string('last_error', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['is_active', 'next_run_at']);
            });
        }

        if (! Schema::hasColumn('push_notifications', 'push_schedule_id')) {
            Schema::table('push_notifications', function (Blueprint $table) {
                $table->foreignId('push_schedule_id')->nullable()->constrained('push_schedules')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Never drop schedules or the history that points at them.
    }
};
