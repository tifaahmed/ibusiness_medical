<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            // Existing rows were all added or vetted by staff, so they stay approved.
            $table->string('approval_status', 20)->default('approved')->after('is_active')->index();
            $table->string('added_by', 20)->default('admin')->after('approval_status');
            $table->string('national_id', 20)->nullable()->after('phone');
            $table->timestamp('approved_at')->nullable()->after('added_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->string('rejection_reason')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'added_by', 'national_id', 'approved_at', 'approved_by', 'rejection_reason']);
        });
    }
};
