<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('member_payments', 'type')) {
            return;
        }

        Schema::table('member_payments', function (Blueprint $table) {
            $table->string('type', 20)->default('commission')->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('member_payments', 'type')) {
            return;
        }

        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
