<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('membership_number')->unique();
            $table->string('national_id', 14)->nullable();
            $table->string('slug')->unique();
            $table->dateTime('registration_date')->nullable();
            $table->dateTime('expiration_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->json('job_title')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->string('payment_type')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_by');
            $table->index('is_active');
            $table->index('is_visible');
            $table->index('is_paid');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
