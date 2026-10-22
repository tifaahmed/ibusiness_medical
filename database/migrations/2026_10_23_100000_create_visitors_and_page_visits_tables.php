<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `visitors` is one row per device/browser (the client's own `visitor_key`);
     * `page_visits` is one row per page or screen it opened.
     *
     * Everything about a visitor is nullable and filled in as it is learned: the
     * row is created on the first hit with the IP and user agent, and enriched
     * later with GPS, governorate/city, phone, email and the signed-in member.
     */
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 80)->unique();
            $table->string('source', 10)->index(); // web | android | ios

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 10)->nullable(); // desktop | mobile | tablet | bot
            $table->string('device_model', 100)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('locale', 5)->nullable();
            $table->text('first_referrer')->nullable();
            $table->boolean('is_bot')->default(false)->index();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable();
            $table->timestamp('location_at')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('governorate_name')->nullable();
            $table->string('city_name')->nullable();

            // `user_id` / `membership_id` are VERIFIED (token or key-gated caller);
            // name / phone / email are whatever the visitor typed into a form.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->timestamp('identified_at')->nullable();

            $table->unsignedInteger('visits_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10)->index();
            $table->string('page', 100)->index();
            $table->text('url')->nullable();
            $table->string('locale', 5)->nullable();
            $table->text('referrer')->nullable();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_bot')->default(false);
            $table->timestamp('visited_at')->index();
            // The old website row this came from — makes the import re-runnable.
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['product_id', 'visited_at']);
            $table->index(['facility_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
        Schema::dropIfExists('visitors');
    }
};
